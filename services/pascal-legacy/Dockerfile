FROM python:3.11-slim

RUN apt-get update && apt-get install -y --no-install-recommends postgresql-client && rm -rf /var/lib/apt/lists/*

WORKDIR /opt/legacy
COPY legacy.py ./
RUN chmod +x legacy.py

CMD ["python3", "legacy.py"]
