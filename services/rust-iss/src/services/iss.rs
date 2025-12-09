use std::time::Duration;
use serde_json::Value;
use sqlx::PgPool;

pub async fn fetch_and_store_iss(pool: &PgPool, url: &str) -> anyhow::Result<()> {
    let client = reqwest::Client::builder().timeout(Duration::from_secs(20)).build()?;
    let resp = client.get(url).send().await?;
    let json: Value = resp.json().await?;
    sqlx::query("INSERT INTO iss_fetch_log (source_url, payload) VALUES ($1, $2)")
        .bind(url).bind(json).execute(pool).await?;
    Ok(())
}
