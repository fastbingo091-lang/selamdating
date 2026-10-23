(() => {
    const feed = document.querySelector('.tiktok-feed');
    if (!feed || !('IntersectionObserver' in window)) {
        return;
    }

    const videos = new Map();
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            const video = videos.get(entry.target);
            if (!video) {
                return;
            }

            if (entry.isIntersecting && entry.intersectionRatio >= 0.65 && !document.hidden) {
                video.play().catch(() => {
                    video.controls = true;
                });
            } else {
                video.pause();
            }
        });
    }, {
        root: feed,
        threshold: [0, 0.65, 1],
    });

    feed.querySelectorAll('.tiktok-profile-card').forEach((card) => {
        const video = card.querySelector('video');
        if (video) {
            videos.set(card, video);
            observer.observe(card);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            videos.forEach((video) => video.pause());
        }
    });
})();
