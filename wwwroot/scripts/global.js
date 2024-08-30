const rewriteEpoch = () => {
    document.querySelectorAll('.unixepoch').forEach((elem) => {
        const date = new Date(elem.dataset.epoch * 1000);
        elem.innerText = date.toLocaleString();
    })
};

const rewriteCountryCode = () => { 
    const intlDisplayName = new Intl.DisplayNames([navigator.language], {type: 'region'});
    document.querySelectorAll('.countrycode').forEach((elem) => {
        try {
            const countryCode = elem.dataset.ccode;
            const countryName = intlDisplayName.of(countryCode);
            elem.innerText = `${countryName} (${countryCode})`;
        }
        catch { 
            console.log('Failed to process country code', elem);
        }
    });
}

const nanoToSeconds = (nano) => Math.floor(nano / 1000000000);

if (typeof module !== 'undefined') {
    module.exports = {
        nanoToSeconds,
    };
}

if (typeof document !== 'undefined') {
    document.onload = () => {
        rewriteEpoch();
        rewriteCountryCode();
    };
}