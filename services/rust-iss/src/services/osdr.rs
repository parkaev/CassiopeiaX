use std::time::Duration;
use serde_json::Value;
use crate::types::AppState;
use crate::repo::osdr_repo;

pub async fn fetch_and_store_osdr(st: &AppState) -> anyhow::Result<usize> {
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let resp = client.get(&st.nasa_url).send().await?;
    if !resp.status().is_success() {
        anyhow::bail!("OSDR request status {}", resp.status());
    }
    let json: Value = resp.json().await?;
    
    let mut written = 0usize;
    
    // Handle {"OSD-1": {...}, "OSD-2": {...}} format
    if let Some(obj) = json.as_object() {
        for (key, val) in obj {
            if key.starts_with("OSD-") {
                osdr_repo::upsert(&st.pool, key, None, None, val.clone()).await?;
                written += 1;
            }
        }
    }
    
    Ok(written)
}
