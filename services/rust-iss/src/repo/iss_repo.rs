use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub struct IssRecord {
    pub id: i64,
    pub fetched_at: DateTime<Utc>,
    pub source_url: String,
    pub payload: Value,
}

pub async fn get_last(pool: &PgPool) -> Result<Option<IssRecord>, sqlx::Error> {
    sqlx::query(
        "SELECT id, fetched_at, source_url, payload FROM iss_fetch_log ORDER BY id DESC LIMIT 1"
    )
    .fetch_optional(pool)
    .await
    .map(|opt| opt.map(|r| IssRecord {
        id: r.get("id"),
        fetched_at: r.get("fetched_at"),
        source_url: r.get("source_url"),
        payload: r.try_get("payload").unwrap_or(serde_json::json!({})),
    }))
}

pub async fn get_last_two(pool: &PgPool) -> Result<Vec<(DateTime<Utc>, Value)>, sqlx::Error> {
    let rows = sqlx::query("SELECT fetched_at, payload FROM iss_fetch_log ORDER BY id DESC LIMIT 2")
        .fetch_all(pool).await?;
    Ok(rows.into_iter().map(|r| (r.get("fetched_at"), r.get("payload"))).collect())
}

pub async fn insert(pool: &PgPool, source_url: &str, payload: Value) -> Result<(), sqlx::Error> {
    sqlx::query("INSERT INTO iss_fetch_log(source_url, payload) VALUES ($1, $2)")
        .bind(source_url).bind(payload).execute(pool).await?;
    Ok(())
}
