use serde_json::Value;
use sqlx::PgPool;
use crate::repo::cache_repo;

pub async fn write_cache(pool: &PgPool, source: &str, payload: Value) -> anyhow::Result<()> {
    cache_repo::write(pool, source, payload).await?;
    Ok(())
}

pub async fn latest_from_cache(pool: &PgPool, src: &str) -> Value {
    cache_repo::get_latest(pool, src)
        .await
        .map(|(at, payload)| serde_json::json!({"at": at, "payload": payload}))
        .unwrap_or(serde_json::json!({}))
}
