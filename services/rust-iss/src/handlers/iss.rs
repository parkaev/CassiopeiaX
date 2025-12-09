use axum::{extract::State, http::StatusCode, Json};
use serde_json::Value;
use crate::types::{AppState, Trend};
use crate::services::iss::fetch_and_store_iss;
use crate::repo::iss_repo;
use crate::utils::{num, haversine_km};

pub async fn last_iss(State(st): State<AppState>) -> Result<Json<Value>, (StatusCode, String)> {
    let record = iss_repo::get_last(&st.pool).await
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))?;

    match record {
        Some(r) => Ok(Json(serde_json::json!({
            "id": r.id, "fetched_at": r.fetched_at, "source_url": r.source_url, "payload": r.payload
        }))),
        None => Ok(Json(serde_json::json!({"message":"no data"}))),
    }
}

pub async fn trigger_iss(State(st): State<AppState>) -> Result<Json<Value>, (StatusCode, String)> {
    fetch_and_store_iss(&st.pool, &st.fallback_url).await
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))?;
    last_iss(State(st)).await
}

pub async fn iss_trend(State(st): State<AppState>) -> Result<Json<Trend>, (StatusCode, String)> {
    let rows = iss_repo::get_last_two(&st.pool).await
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))?;

    if rows.len() < 2 {
        return Ok(Json(Trend::default()));
    }

    let (t2, p2) = &rows[0];
    let (t1, p1) = &rows[1];

    let lat1 = num(&p1["latitude"]);
    let lon1 = num(&p1["longitude"]);
    let lat2 = num(&p2["latitude"]);
    let lon2 = num(&p2["longitude"]);

    let (delta_km, movement) = match (lat1, lon1, lat2, lon2) {
        (Some(a1), Some(o1), Some(a2), Some(o2)) => {
            let d = haversine_km(a1, o1, a2, o2);
            (d, d > 0.1)
        }
        _ => (0.0, false),
    };

    Ok(Json(Trend {
        movement,
        delta_km,
        dt_sec: (*t2 - *t1).num_milliseconds() as f64 / 1000.0,
        velocity_kmh: num(&p2["velocity"]),
        from_time: Some(*t1), to_time: Some(*t2),
        from_lat: lat1, from_lon: lon1, to_lat: lat2, to_lon: lon2,
    }))
}
