use std::time::Duration;
use serde_json::Value;
use sqlx::PgPool;
use crate::repo::iss_repo;

pub async fn fetch_and_store_iss(pool: &PgPool, url: &str) -> anyhow::Result<()> {
    let client = reqwest::Client::builder().timeout(Duration::from_secs(20)).build()?;
    let resp = client.get(url).send().await?;
    let json: Value = resp.json().await?;
    iss_repo::insert(pool, url, json).await?;
    Ok(())
}
