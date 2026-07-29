(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (!root || !root.document) {
        return;
    }

    root.ICEFOX_TOP_ICON_CONTRAST = api;

    const start = function () {
        api.init(root.document, root);
    };

    if (root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})(typeof window !== 'undefined' ? window : globalThis, function () {
    const DARK_ICON_LUMINANCE = relativeLuminance(85, 85, 85);

    function relativeLuminance(red, green, blue) {
        const channels = [red, green, blue].map(function (channel) {
            const value = Math.max(0, Math.min(255, channel)) / 255;
            return value <= 0.04045
                ? value / 12.92
                : Math.pow((value + 0.055) / 1.055, 2.4);
        });

        return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);
    }

    function contrastRatio(firstLuminance, secondLuminance) {
        const lighter = Math.max(firstLuminance, secondLuminance);
        const darker = Math.min(firstLuminance, secondLuminance);
        return (lighter + 0.05) / (darker + 0.05);
    }

    function prefersDarkIconsForLuminance(backgroundLuminance) {
        const darkContrast = contrastRatio(backgroundLuminance, DARK_ICON_LUMINANCE);
        const lightContrast = contrastRatio(backgroundLuminance, 1);
        return darkContrast >= lightContrast;
    }

    function prefersDarkIcons(red, green, blue) {
        return prefersDarkIconsForLuminance(relativeLuminance(red, green, blue));
    }

    function averageLuminance(pixelData) {
        let luminanceTotal = 0;
        let alphaTotal = 0;

        for (let index = 0; index < pixelData.length; index += 4) {
            const alpha = pixelData[index + 3] / 255;
            if (alpha === 0) continue;

            luminanceTotal += relativeLuminance(
                pixelData[index],
                pixelData[index + 1],
                pixelData[index + 2]
            ) * alpha;
            alphaTotal += alpha;
        }

        return alphaTotal === 0 ? null : luminanceTotal / alphaTotal;
    }

    function coverGeometry(mediaWidth, mediaHeight, containerWidth, containerHeight) {
        const scale = Math.max(containerWidth / mediaWidth, containerHeight / mediaHeight);
        const width = mediaWidth * scale;
        const height = mediaHeight * scale;

        return {
            width: width,
            height: height,
            left: (containerWidth - width) / 2,
            top: (containerHeight - height) / 2
        };
    }

    function backgroundImageUrl(backgroundImage) {
        if (!backgroundImage || backgroundImage === 'none') return '';

        const match = backgroundImage.trim().match(/^url\((['"]?)([\s\S]*)\1\)$/);
        return match ? match[2] : '';
    }

    function backgroundColorChannels(backgroundColor) {
        const values = String(backgroundColor).match(/[\d.]+/g);
        if (!values || values.length < 3) return null;
        if (values.length > 3 && Number(values[3]) === 0) return null;

        return values.slice(0, 3).map(Number);
    }

    function init(documentObject, windowObject) {
        const topContainer = documentObject.querySelector('.top-container');
        const header = documentObject.querySelector('.header-container');

        if (!topContainer || !header) {
            return { destroy: function () {} };
        }

        const canvas = documentObject.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });
        const activeVideo = header.querySelector('video');
        const imageCache = new Map();
        let activeImage = null;
        let analysisVersion = 0;
        let animationFrame = 0;
        let destroyed = false;

        function loadImage(url) {
            if (imageCache.has(url)) {
                return imageCache.get(url);
            }

            const request = new Promise(function (resolve, reject) {
                const image = new windowObject.Image();
                let absoluteUrl;

                try {
                    absoluteUrl = new windowObject.URL(url, windowObject.location.href);
                } catch (error) {
                    reject(error);
                    return;
                }

                if (absoluteUrl.origin !== windowObject.location.origin) {
                    image.crossOrigin = 'anonymous';
                }

                image.onload = function () {
                    resolve(image);
                };
                image.onerror = function () {
                    reject(new Error('Unable to read the header background image'));
                };
                image.src = absoluteUrl.href;
            });

            imageCache.set(url, request);
            request.catch(function () {
                imageCache.delete(url);
            });
            return request;
        }

        function sampleActiveMedia() {
            if (!context) return null;

            const hasVideoFrame = activeVideo
                && activeVideo.videoWidth > 0
                && activeVideo.videoHeight > 0;
            const media = hasVideoFrame ? activeVideo : activeImage;
            const mediaWidth = hasVideoFrame ? activeVideo.videoWidth : (activeImage && activeImage.naturalWidth);
            const mediaHeight = hasVideoFrame ? activeVideo.videoHeight : (activeImage && activeImage.naturalHeight);
            if (!media || !mediaWidth || !mediaHeight) return null;

            const headerBounds = header.getBoundingClientRect();
            const topBounds = topContainer.getBoundingClientRect();
            if (headerBounds.width <= 0 || headerBounds.height <= 0) return null;

            const sampleTop = Math.max(0, topBounds.top - headerBounds.top);
            const sampleHeight = Math.min(topBounds.height, headerBounds.height - sampleTop);
            if (sampleHeight <= 0) return null;

            const sampleWidth = Math.min(64, Math.max(1, Math.round(headerBounds.width)));
            const sampleScale = sampleWidth / headerBounds.width;
            const geometry = coverGeometry(
                mediaWidth,
                mediaHeight,
                headerBounds.width,
                headerBounds.height
            );

            canvas.width = sampleWidth;
            canvas.height = Math.max(1, Math.round(sampleHeight * sampleScale));
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.drawImage(
                media,
                geometry.left * sampleScale,
                (geometry.top - sampleTop) * sampleScale,
                geometry.width * sampleScale,
                geometry.height * sampleScale
            );

            return averageLuminance(
                context.getImageData(0, 0, canvas.width, canvas.height).data
            );
        }

        function updateContrast() {
            if (destroyed) return;

            let luminance = null;
            let samplingFailed = false;
            try {
                luminance = sampleActiveMedia();
            } catch (error) {
                luminance = null;
                samplingFailed = true;
            }

            if (luminance === null && !activeImage) {
                const backgroundColor = windowObject.getComputedStyle(header).backgroundColor;
                const channels = backgroundColorChannels(backgroundColor);
                if (channels) {
                    luminance = relativeLuminance(channels[0], channels[1], channels[2]);
                }
            }

            topContainer.classList.toggle(
                'has-light-background',
                luminance !== null && prefersDarkIconsForLuminance(luminance)
            );
            topContainer.classList.toggle(
                'has-media-sampling-fallback',
                Boolean(activeVideo) && (samplingFailed || luminance === null)
            );
        }

        function scheduleContrastUpdate() {
            if (animationFrame || destroyed) return;
            animationFrame = windowObject.requestAnimationFrame(function () {
                animationFrame = 0;
                updateContrast();
            });
        }

        function refreshBackgroundSource() {
            const version = ++analysisVersion;
            const backgroundImage = windowObject.getComputedStyle(header).backgroundImage;
            const imageUrl = backgroundImageUrl(backgroundImage);

            activeImage = null;
            updateContrast();
            if (!imageUrl) {
                return;
            }

            loadImage(imageUrl).then(function (image) {
                if (destroyed || version !== analysisVersion) return;
                activeImage = image;
                updateContrast();
            }).catch(function () {
                if (destroyed || version !== analysisVersion) return;
                activeImage = null;
                updateContrast();
            });
        }

        const MutationObserver = windowObject.MutationObserver;
        const observer = new MutationObserver(refreshBackgroundSource);
        const videoEvents = ['loadeddata', 'timeupdate', 'seeked'];
        observer.observe(header, { attributes: true, attributeFilter: ['style'] });
        if (activeVideo) {
            videoEvents.forEach(function (eventName) {
                activeVideo.addEventListener(eventName, scheduleContrastUpdate);
            });
        }
        windowObject.addEventListener('scroll', scheduleContrastUpdate, { passive: true });
        windowObject.addEventListener('resize', scheduleContrastUpdate);
        refreshBackgroundSource();

        return {
            destroy: function () {
                destroyed = true;
                observer.disconnect();
                if (activeVideo) {
                    videoEvents.forEach(function (eventName) {
                        activeVideo.removeEventListener(eventName, scheduleContrastUpdate);
                    });
                }
                windowObject.removeEventListener('scroll', scheduleContrastUpdate);
                windowObject.removeEventListener('resize', scheduleContrastUpdate);
                if (animationFrame) {
                    windowObject.cancelAnimationFrame(animationFrame);
                }
            }
        };
    }

    return {
        relativeLuminance: relativeLuminance,
        prefersDarkIcons: prefersDarkIcons,
        averageLuminance: averageLuminance,
        coverGeometry: coverGeometry,
        init: init
    };
});
