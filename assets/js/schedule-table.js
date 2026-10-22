'use strict';

const schedulePane = document.querySelector('.schedule-table-scroll');
const scheduleTopScroll = document.querySelector('.schedule-scroll-top');
if (schedulePane && scheduleTopScroll) {
    const table = schedulePane.querySelector('table');
    const resize = () => {
        scheduleTopScroll.style.width = `${schedulePane.clientWidth}px`;
        scheduleTopScroll.firstElementChild.style.width = `${table.scrollWidth}px`;
        scheduleTopScroll.hidden = table.scrollWidth <= schedulePane.clientWidth;
        scheduleTopScroll.scrollLeft = schedulePane.scrollLeft;
    };
    scheduleTopScroll.addEventListener('scroll', () => {
        if (schedulePane.scrollLeft !== scheduleTopScroll.scrollLeft) schedulePane.scrollLeft = scheduleTopScroll.scrollLeft;
    }, { passive: true });
    schedulePane.addEventListener('scroll', () => {
        if (scheduleTopScroll.scrollLeft !== schedulePane.scrollLeft) scheduleTopScroll.scrollLeft = schedulePane.scrollLeft;
    }, { passive: true });
    resize();
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(resize);
        observer.observe(table);
        observer.observe(schedulePane);
    } else window.addEventListener('resize', resize);
}
