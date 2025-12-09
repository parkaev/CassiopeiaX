use std::time::Duration;
use chrono::Utc;
use serde_json::Value;
use crate::types::AppState;
use crate::services::space_cache::write_cache;
use crate::utils::last_days;

pub async fn fetch_apod(st: &AppState) -> anyhow::Result<()> {
    st.nasa_limiter.wait().await;
    let url = "https://api.nasa.gov/planetary/apod";
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let mut req = client.get(url).query(&[("thumbs","true")]);
    if !st.nasa_key.is_empty() { req = req.query(&[("api_key",&st.nasa_key)]); }
    let json: Value = req.send().await?.json().await?;
    write_cache(&st.pool, "apod", json).await
}

pub async fn fetch_neo_feed(st: &AppState) -> anyhow::Result<()> {
    st.nasa_limiter.wait().await;
    let today = Utc::now().date_naive();
    let start = today - chrono::Days::new(2);
    let url = "https://api.nasa.gov/neo/rest/v1/feed";
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let mut req = client.get(url).query(&[
        ("start_date", start.to_string()),
        ("end_date", today.to_string()),
    ]);
    if !st.nasa_key.is_empty() { req = req.query(&[("api_key",&st.nasa_key)]); }
    let json: Value = req.send().await?.json().await?;
    write_cache(&st.pool, "neo", json).await
}

pub async fn fetch_donki(st: &AppState) -> anyhow::Result<()> {
    let _ = fetch_donki_flr(st).await;
    let _ = fetch_donki_cme(st).await;
    Ok(())
}

pub async fn fetch_donki_flr(st: &AppState) -> anyhow::Result<()> {
    st.nasa_limiter.wait().await;
    let (from,to) = last_days(5);
    let url = "https://api.nasa.gov/DONKI/FLR";
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let mut req = client.get(url).query(&[("startDate",from),("endDate",to)]);
    if !st.nasa_key.is_empty() { req = req.query(&[("api_key",&st.nasa_key)]); }
    let json: Value = req.send().await?.json().await?;
    write_cache(&st.pool, "flr", json).await
}

pub async fn fetch_donki_cme(st: &AppState) -> anyhow::Result<()> {
    st.nasa_limiter.wait().await;
    let (from,to) = last_days(5);
    let url = "https://api.nasa.gov/DONKI/CME";
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let mut req = client.get(url).query(&[("startDate",from),("endDate",to)]);
    if !st.nasa_key.is_empty() { req = req.query(&[("api_key",&st.nasa_key)]); }
    let json: Value = req.send().await?.json().await?;
    write_cache(&st.pool, "cme", json).await
}

pub async fn fetch_spacex_next(st: &AppState) -> anyhow::Result<()> {
    st.spacex_limiter.wait().await;
    let url = "https://api.spacexdata.com/v4/launches/next";
    let client = reqwest::Client::builder().timeout(Duration::from_secs(30)).build()?;
    let json: Value = client.get(url).send().await?.json().await?;
    write_cache(&st.pool, "spacex", json).await
}
