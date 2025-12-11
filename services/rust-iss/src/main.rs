use std::time::Duration;
use axum::{routing::get, Json, Router};
use chrono::Utc;
use tracing::{error, info, warn};
use tracing_subscriber::{EnvFilter, FmtSubscriber};

use rust_iss::{
    config::create_app_state,
    database::init_db,
    handlers::{iss::*, osdr::*, space::*},
    lock::{lock_id, with_advisory_lock},
    services::{iss::fetch_and_store_iss, nasa::*, osdr::fetch_and_store_osdr},
    types::Health,
};

#[tokio::main]
async fn main() -> anyhow::Result<()> {
    let subscriber = FmtSubscriber::builder()
        .with_env_filter(EnvFilter::from_default_env())
        .finish();
    let _ = tracing::subscriber::set_global_default(subscriber);

    let state = create_app_state().await?;
    init_db(&state.pool).await?;

    spawn_background_tasks(&state).await;

    let app = Router::new()
        .route("/health", get(|| async { Json(Health { status: "ok", now: Utc::now() }) }))
        .route("/last", get(last_iss))
        .route("/fetch", get(trigger_iss))
        .route("/iss/trend", get(iss_trend))
        .route("/osdr/sync", get(osdr_sync))
        .route("/osdr/list", get(osdr_list))
        .route("/space/:src/latest", get(space_latest))
        .route("/space/refresh", get(space_refresh))
        .route("/space/summary", get(space_summary))
        .with_state(state);

    let listener = tokio::net::TcpListener::bind(("0.0.0.0", 3000)).await?;
    info!("rust_iss listening on 0.0.0.0:3000");
    axum::serve(listener, app.into_make_service()).await?;
    Ok(())
}

async fn spawn_background_tasks(state: &rust_iss::types::AppState) {
    // OSDR
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("osdr");
            loop {
                let st2 = st.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_and_store_osdr(&st2).await { error!("osdr err {e:?}") }
                }).await.is_none() {
                    warn!("osdr lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_osdr)).await;
            }
        });
    }
    
    // ISS
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("iss");
            loop {
                let pool = st.pool.clone();
                let url = st.fallback_url.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_and_store_iss(&pool, &url).await { error!("iss err {e:?}") }
                }).await.is_none() {
                    warn!("iss lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_iss)).await;
            }
        });
    }
    
    // APOD
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("apod");
            loop {
                let st2 = st.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_apod(&st2).await { error!("apod err {e:?}") }
                }).await.is_none() {
                    warn!("apod lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_apod)).await;
            }
        });
    }
    
    // NEO
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("neo");
            loop {
                let st2 = st.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_neo_feed(&st2).await { error!("neo err {e:?}") }
                }).await.is_none() {
                    warn!("neo lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_neo)).await;
            }
        });
    }
    
    // DONKI
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("donki");
            loop {
                let st2 = st.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_donki(&st2).await { error!("donki err {e:?}") }
                }).await.is_none() {
                    warn!("donki lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_donki)).await;
            }
        });
    }
    
    // SpaceX
    {
        let st = state.clone();
        tokio::spawn(async move {
            let lid = lock_id("spacex");
            loop {
                let st2 = st.clone();
                if with_advisory_lock(&st.pool, lid, || async {
                    if let Err(e) = fetch_spacex_next(&st2).await { error!("spacex err {e:?}") }
                }).await.is_none() {
                    warn!("spacex lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_spacex)).await;
            }
        });
    }
}
