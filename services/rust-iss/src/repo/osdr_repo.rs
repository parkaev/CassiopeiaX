use chrono::{DateTime, Utc};
use serde_json::Value;
use sqlx::{PgPool, Row};

pub struct OsdrItem {
    pub id: i64,
    pub dataset_id: Option<String>,
    pub title: Option<String>,
    pub status: Option<String>,
    pub updated_at: Option<DateTime<Utc>>,
    pub inserted_at: DateTime<Utc>,
    pub raw: Value,
}

pub async fn list(pool: &PgPool, limit: i64) -> Result<Vec<OsdrItem>, sqlx::Error> {
    let rows = sqlx::query(
        "SELECT id, dataset_id, title, status, updated_at, inserted_at, raw
         FROM osdr_items ORDER BY inserted_at DESC LIMIT $1"
    ).bind(limit).fetch_all(pool).await?;

    Ok(rows.into_iter().map(|r| OsdrItem {
        id: r.get("id"),
        dataset_id: r.get("dataset_id"),
        title: r.get("title"),
        status: r.get("status"),
        updated_at: r.get("updated_at"),
        inserted_at: r.get("inserted_at"),
        raw: r.get("raw"),
    }).collect())
}

pub async fn upsert(pool: &PgPool, dataset_id: &str, title: Option<&str>, status: Option<&str>, raw: Value) -> Result<(), sqlx::Error> {
    sqlx::query(
        "INSERT INTO osdr_items(dataset_id, title, status, raw)
         VALUES ($1, $2, $3, $4)
         ON CONFLICT (dataset_id) DO UPDATE SET title=EXCLUDED.title, status=EXCLUDED.status, raw=EXCLUDED.raw, updated_at=now()"
    ).bind(dataset_id).bind(title).bind(status).bind(raw).execute(pool).await?;
    Ok(())
}
