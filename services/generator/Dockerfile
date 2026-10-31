FROM python:3.11-slim

WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*

COPY services/generator/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY services/generator/app ./app
COPY apps/web/public/images/tokamaki_label.webp ./app/assets/tokamaki_label.webp
RUN cp /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf /app/app/assets/DejaVuSans.ttf \
    && mkdir -p /app/data/results
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]

