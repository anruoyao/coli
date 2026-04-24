window.embedder = function(path, defaultValue = undefined) {
    return path.split('.').reduce((acc, part) => {
        return acc && acc[part] !== undefined ? acc[part] : undefined;
    }, window.BackendEmbeds) || defaultValue;
}

window.config = function(path, defaultValue = undefined) {
    return embedder(`config.${path}`, defaultValue);
}

window.base_url = function(path = '', defaultValue = undefined) {
    return embedder(`links.base_url`) + path;
}

window.freezeScroll = function() {
    window.ACTIVE_MODALS = window.ACTIVE_MODALS || 0;

    window.ACTIVE_MODALS++;

    document.body.style.overflow = 'hidden';
}

window.unfreezeScroll = function() {
    window.ACTIVE_MODALS--;

    if (window.ACTIVE_MODALS < 1) {
        document.body.style.overflow = 'auto';
    }
}