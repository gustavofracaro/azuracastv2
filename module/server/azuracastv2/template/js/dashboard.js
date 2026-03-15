(function () {
    const wrap = document.querySelector('.azv2-wrap');
    if (!wrap) {
        return;
    }

    const stationId = wrap.dataset.stationId || '0';
    if (stationId === '0') {
        console.warn('Azuracast V2: estação ainda não provisionada para este serviço.');
    }
})();
