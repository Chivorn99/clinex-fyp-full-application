FROM php:8.2-cli-bookworm

# PaddlePaddle variant selection:
# - cpu (default): installs paddlepaddle from PyPI via requirements.txt
# - gpu: swaps paddlepaddle -> paddlepaddle-gpu from Paddle's CUDA wheel index
ARG PADDLE_VARIANT=cpu
ARG PADDLE_GPU_VERSION=3.3.0
ARG PADDLE_GPU_INDEX_URL=https://www.paddlepaddle.org.cn/packages/stable/cu126/

WORKDIR /var/www/html

# System dependencies for Laravel + OCR helpers.
# libgl1 + libglib2.0-0 + libxcb1 are needed by OpenCV (used by Kiri OCR).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        python3 \
        python3-pip \
        libgl1 \
        libglib2.0-0 \
        libxcb1 \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip bcmath pcntl intl gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy manifests first for better layer caching.
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-progress || true

COPY package.json package-lock.json* ./
COPY scripts/python/requirements.txt /tmp/requirements.txt

# Replace opencv-python with headless variant (no X11/GUI deps required).
# Do this BEFORE installing requirements so kiri-ocr reuses the headless build.
RUN pip3 install --no-cache-dir --break-system-packages \
        opencv-python-headless>=4.9.0

# Stage 1: Install CPU-only PyTorch first (much smaller than full CUDA bundle).
# This prevents SIGBUS/OOM during Docker builds on resource-limited hosts (WSL2).
RUN pip3 install --no-cache-dir --break-system-packages \
        torch torchvision --index-url https://download.pytorch.org/whl/cpu || true

# Stage 2: Install remaining OCR dependencies (kiri-ocr will reuse the CPU torch).
RUN pip3 install --no-cache-dir --break-system-packages \
        -r /tmp/requirements.txt || true

# Optional: build a GPU-enabled image when you have NVIDIA runtime available.
# This keeps the default image CPU-friendly for teammates.
RUN if [ "$PADDLE_VARIANT" = "gpu" ]; then \
        pip3 uninstall -y paddlepaddle || true; \
        pip3 install --no-cache-dir --break-system-packages \
            "paddlepaddle-gpu==${PADDLE_GPU_VERSION}" -i "${PADDLE_GPU_INDEX_URL}"; \
    fi

COPY . .

EXPOSE 8000

CMD ["sh", "-lc", "composer install --no-interaction --prefer-dist --no-progress && php artisan key:generate --force && php artisan serve --host=0.0.0.0 --port=8000"]
