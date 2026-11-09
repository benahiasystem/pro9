FROM pruebas-php AS codec-builder

USER root

# Native AVIF support preserves alpha and dimensions when validating and
# re-encoding configuration images. Keep the existing GD formats enabled.
# Bullseye's security packages moved to Debian's official archive.
RUN sed -i 's|http://deb.debian.org/debian-security|https://archive.debian.org/debian-security|g; s|http://deb.debian.org|https://deb.debian.org|g; s|http://security.debian.org|https://archive.debian.org|g' /etc/apt/sources.list \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get -o Acquire::https::No-Cache=true update \
    && apt-get install -y --no-install-recommends \
        cmake nasm debian-archive-keyring libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && printf 'deb-src https://deb.debian.org/debian bookworm main\ndeb-src https://security.debian.org/debian-security bookworm-security main\n' > /etc/apt/sources.list.d/pro9-codec-sources.list \
    && apt-get -o Acquire::https::No-Cache=true update \
    && mkdir -p /tmp/pro9-avif-build \
    && cd /tmp/pro9-avif-build \
    && apt-get source aom libavif

# Build Debian's patched codec sources with encoding enabled. Source-only
# Bookworm repositories above do not upgrade the base operating system.
RUN cd /tmp/pro9-avif-build \
    && cmake -S aom-* -B codec-build \
        -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/local -DCMAKE_INSTALL_LIBDIR=lib \
        -DBUILD_SHARED_LIBS=ON -DENABLE_TESTS=OFF -DENABLE_DOCS=OFF \
        -DENABLE_EXAMPLES=OFF -DENABLE_TOOLS=OFF \
        -DCONFIG_LIBYUV=0 -DCONFIG_TUNE_BUTTERAUGLI=0 \
    && cmake --build codec-build --parallel 2 \
    && cmake --install codec-build

RUN cd /tmp/pro9-avif-build \
    && cmake -S libavif-* -B build \
        -DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX=/usr/local -DCMAKE_INSTALL_LIBDIR=lib \
        -DCMAKE_PREFIX_PATH=/usr/local \
        -DBUILD_SHARED_LIBS=ON -DAVIF_CODEC_AOM=ON -DAVIF_CODEC_LIBGAV1=OFF \
        -DAVIF_BUILD_APPS=OFF -DAVIF_BUILD_TESTS=OFF \
    && cmake --build build --parallel 2 \
    && cmake --install build \
    && ldconfig \
    && PKG_CONFIG_PATH=/usr/local/lib/pkgconfig docker-php-ext-configure gd --with-avif --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd

FROM pruebas-php

USER root

COPY --from=codec-builder /usr/local/lib/libaom.so* /usr/local/lib/
COPY --from=codec-builder /usr/local/lib/libavif.so* /usr/local/lib/
COPY --from=codec-builder /usr/local/lib/php/extensions/no-debug-non-zts-20220829/gd.so /usr/local/lib/php/extensions/no-debug-non-zts-20220829/gd.so

RUN ldconfig

USER ubuntu
