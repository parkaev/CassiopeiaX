use crate::types::AppState;
use sqlx::postgres::PgPoolOptions;

pub fn env_u64(k: &str, d: u64) -> u64 {
    std::env::var(k).ok().and_then(|s| s.parse().ok()).unwrap_or(d)
}

pub async fn create_app_state() -> anyhow::Result<AppState> {
    dotenvy::dotenv().ok();

    let db_url = std::env::var("DATABASE_URL").expect("DATABASE_URL is required");
    let nasa_url = std::env::var("NASA_API_URL")
        .unwrap_or_else(|_| "https://visualization.osdr.nasa.gov/biodata/api/v2/datasets/?format=json".to_string());
    let nasa_key = std::env::var("NASA_API_KEY").unwrap_or_default();
    let fallback_url = std::env::var("WHERE_ISS_URL")
        .unwrap_or_else(|_| "https://api.wheretheiss.at/v1/satellites/25544".to_string());

    let every_osdr = env_u64("FETCH_EVERY_SECONDS", 600);
    let every_iss = env_u64("ISS_EVERY_SECONDS", 120);
    let every_apod = env_u64("APOD_EVERY_SECONDS", 43200);
    let every_neo = env_u64("NEO_EVERY_SECONDS", 7200);
    let every_donki = env_u64("DONKI_EVERY_SECONDS", 3600);
    let every_spacex = env_u64("SPACEX_EVERY_SECONDS", 3600);

    let pool = PgPoolOptions::new().max_connections(5).connect(&db_url).await?;

    Ok(AppState {
        pool,
        nasa_url,
        nasa_key,
        fallback_url,
        every_osdr,
        every_iss,
        every_apod,
        every_neo,
        every_donki,
        every_spacex,
    })
}
