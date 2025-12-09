use axum::{extract::State, http::StatusCode, Json};
use serde_json::Value;
use crate::types::AppState;
use crate::services::osdr::fetch_and_store_osdr;
use crate::repo::osdr_repo;

pub async fn osdr_sync(State(st): State<AppState>) -> Result<Json<Value>, (StatusCode, String)> {
    let written = fetch_and_store_osdr(&st).await
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))?;
    Ok(Json(serde_json::json!({ "written": written })))
}

pub async fn osdr_list(State(st): State<AppState>) -> Result<Json<Value>, (StatusCode, String)> {
    let limit = std::env::var("OSDR_LIST_LIMIT").ok()
        .and_then(|s| s.parse::<i64>().ok()).unwrap_or(20);

    let items = osdr_repo::list(&st.pool, limit).await
        .map_err(|e| (StatusCode::INTERNAL_SERVER_ERROR, e.to_string()))?;

    let out: Vec<Value> = items.into_iter().map(|r| serde_json::json!({
        "id": r.id,
        "dataset_id": r.dataset_id,
        "title": r.title,
        "status": r.status,
        "updated_at": r.updated_at,
        "inserted_at": r.inserted_at,
        "raw": r.raw,
    })).collect();

    Ok(Json(serde_json::json!({ "items": out })))
}
