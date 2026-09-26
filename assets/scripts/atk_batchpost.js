const global_nanoToSeconds = (nano) => {
    if (typeof document !== 'undefined') {
        // Browser
        return nanoToSeconds(nano);
    }
    else {
        // Not browser
        const { nanoToSeconds } = require('./global.js');
        return nanoToSeconds(nano);
    }
}

const parseFgtIpsLog = (log) => {
    const lines = log.replace("\r\n", "\n").split("\n");
    let ipsLog = [];
    lines.forEach((line, idx) => {
        if (!line.includes('type="utm" subtype="ips" eventtype="signature"')) {
            console.log(`Line ${idx} isn't Signature IPS Message: '${line}'`);
            return;
        }

        const eventtime = line.match(/eventtime=([0-9]+)/)[1];
        const src = line.match(/srcip=([0-9\.:]+)/)[1];
        const srcport = line.match(/srcport=([0-9]+)/)[1];
        const dst = line.match(/dstip=([0-9\.:]+)/)[1];
        const dstport = line.match(/dstport=([0-9]+)/)[1];
        const msg = line.match(/attack="([^"]+)"/)[1];
        const time = global_nanoToSeconds(eventtime);

        ipsLog.push({
            src: src,
            srcPort: srcport,
            dst: dst,
            dstPort: dstport,
            msg: 'FortiGate IPS Signature: ' + msg,
            epoch: time,
        });
    });
    return ipsLog;
};

if (typeof module !== 'undefined') {
    module.exports = {
        parseFgtIpsLog,
    };
}
