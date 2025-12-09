use std::time::Duration;
use axum::{routing::get, Json, Router};
use chrono::Utc;
use tracing::{error, info, warn};
use tracing_subscriber::{EnvFilter, FmtSubscriber};

use rust_iss::{
    config::create_app_state,
    database::init_db,
    handlers::{iss::*, osdr::*, space::*},
    lock::{lock_id, try_advisory_lock, release_advisory_lock},
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_and_store_osdr(&st).await { error!("osdr err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_and_store_iss(&st.pool, &st.fallback_url).await { error!("iss err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_apod(&st).await { error!("apod err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_neo_feed(&st).await { error!("neo err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_donki(&st).await { error!("donki err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
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
                if try_advisory_lock(&st.pool, lid).await {
                    if let Err(e) = fetch_spacex_next(&st).await { error!("spacex err {e:?}") }
                    release_advisory_lock(&st.pool, lid).await;
                } else {
                    warn!("spacex lock held, skipping");
                }
                tokio::time::sleep(Duration::from_secs(st.every_spacex)).await;
            }
        });
    }
}
