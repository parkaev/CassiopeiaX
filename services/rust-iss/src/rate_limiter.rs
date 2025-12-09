use std::sync::Arc;
use std::time::{Duration, Instant};
use tokio::sync::Mutex;

pub struct RateLimiter {
    last_request: Arc<Mutex<Instant>>,
    min_interval: Duration,
}

impl RateLimiter {
    pub fn new(requests_per_minute: u32) -> Self {
        let interval_ms = if requests_per_minute > 0 {
            60_000 / requests_per_minute as u64
        } else {
            1000
        };
        Self {
            last_request: Arc::new(Mutex::new(Instant::now() - Duration::from_secs(60))),
            min_interval: Duration::from_millis(interval_ms),
        }
    }

    pub async fn wait(&self) {
        let mut last = self.last_request.lock().await;
        let elapsed = last.elapsed();
        if elapsed < self.min_interval {
            tokio::time::sleep(self.min_interval - elapsed).await;
        }
        *last = Instant::now();
    }
}

impl Clone for RateLimiter {
    fn clone(&self) -> Self {
        Self {
            last_request: Arc::clone(&self.last_request),
            min_interval: self.min_interval,
        }
    }
}
