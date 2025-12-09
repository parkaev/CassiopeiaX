use std::time::Duration;
use serde_json::Value;
use crate::types::AppState;
use crate::utils::s_pick;
use crate::repo::osdr_repo;

pub async fn fetch_and_store_osdr(st: &AppState) -> anyhow::Result<usize> {
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let resp = client.get(&st.nasa_url).send().await?;
    if !resp.status().is_success() {
        anyhow::bail!("OSDR request status {}", resp.status());
    }
    let json: Value = resp.json().await?;
    let items = if let Some(a) = json.as_array() { a.clone() }
        else if let Some(v) = json.get("items").and_then(|x| x.as_array()) { v.clone() }
        else if let Some(v) = json.get("results").and_then(|x| x.as_array()) { v.clone() }
        else { vec![json.clone()] };

    let mut written = 0usize;
    for item in items {
        let id = s_pick(&item, &["dataset_id","id","uuid","studyId","accession","osdr_id"]);
        let title = s_pick(&item, &["title","name","label"]);
        let status = s_pick(&item, &["status","state","lifecycle"]);
        
        if let Some(ds) = id {
            osdr_repo::upsert(&st.pool, &ds, title.as_deref(), status.as_deref(), item).await?;
        }
        written += 1;
    }
    Ok(written)
}
