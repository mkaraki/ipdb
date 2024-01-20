const rewriteEpoch = () => {
    document.querySelectorAll('.unixepoch').forEach((elem) => {
        const date = new Date(elem.dataset.epoch * 1000);
        elem.innerText = date.toLocaleString();
    })
};

const nanoToSeconds = (nano) => Math.floor(nano / 1000000000);

rewriteEpoch();