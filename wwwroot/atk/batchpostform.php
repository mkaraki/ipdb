<?php
require_once __DIR__ . '/../_init.php';
authBasic(USER_ATK_MANAGER);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/table.css">
    <title>Batch Add ATK IP Info</title>
</head>

<body>

    <h1>Batch Add IP Info (ATK)</h1>
    <hr />

    <form action="" onsubmit="return false">
        <h2>FortiGate IPS</h2>
        <div>
            Logs:
            <textarea id="fgt-ips-log" cols="30" rows="10"></textarea>
        </div>
        <div>
            <button type="button" onclick="addLog(parseFgtIpsLog(document.getElementById('fgt-ips-log').value))">Parse</button>
        </div>
    </form>

    <div>
        <table class="border">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>[Src]:Port</th>
                    <th>[Dst]:Port</th>
                    <th>Msg</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="batch-preview"></tbody>
        </table>

        <div>
            <button type="button" onclick="batchPost(document.getElementById('batch-preview'), this)">Post</button>
        </div>
    </div>
    <?php require __DIR__ . '/../_legal.php'; ?>


    <script src="../scripts/global.js"></script>
    <script src="../scripts/atk_batchpost.js"></script>
    <script>
        function addLog(logObj) {
            logObj.forEach((v) => {
                const tr = document.createElement('tr');
                const tdTime = document.createElement('td');
                const tdSrc = document.createElement('td');
                const tdDst = document.createElement('td');
                const tdMsg = document.createElement('td');
                const tdAction = document.createElement('td');
                const delBtn = document.createElement('button');

                tdTime.innerText = v.epoch;
                tdTime.classList.add('unixepoch');
                tdTime.dataset.epoch = v.epoch;
                tdSrc.innerText = `[${v.src}]:${v.srcPort}`;
                tdDst.innerText = `[${v.dst}]:${v.dstPort}`;
                tdMsg.innerText = v.msg;
                delBtn.innerText = 'Delete';
                delBtn.addEventListener('click', () => {
                    tr.remove();
                });

                tdAction.appendChild(delBtn);

                tr.appendChild(tdTime);
                tr.appendChild(tdSrc);
                tr.appendChild(tdDst);
                tr.appendChild(tdMsg);
                tr.appendChild(tdAction);

                tr.dataset.logObj = JSON.stringify(v);

                document.getElementById('batch-preview').appendChild(tr);
            });

            rewriteEpoch();
        }

        async function batchPost(table, elem) {
            elem.disabled = true;

            const rows = table.querySelectorAll('tr');
            const data = [];

            rows.forEach(async (v) => {
                data.push({
                    data: JSON.parse(v.dataset.logObj),
                    elem: v
                });
            });

            let reported = [];

            const post = (idx) => {
                if (idx >= data.length) {
                    elem.disabled = false;
                    return;
                }

                const v = data[idx];

                const logData = v.data;

                if (reported.includes(logData.src)) {
                    v.elem.remove();
                    post(idx + 1);
                    return;
                }

                let formData = new FormData();
                formData.append('role_mgr', '1');
                formData.append('noredirect', '1');
                formData.append('ip', logData.src);
                formData.append('loggedat', logData.epoch);

                const goNext = () => {
                    setTimeout(() => {
                        post(idx + 1)
                    }, 400);
                }

                const onFail = () => {
                    console.error('Failed to post', logData);
                    v.elem.setAttribute('style', 'background-color: red !important;')
                    goNext();
                }

                fetch('post.php', {
                        method: 'POST',
                        body: formData,
                    })
                    .then((fetchRes) => {
                        if (fetchRes.status == 303 || fetchRes.status == 200) {
                            v.elem.remove();
                            reported.push(logData.src);
                            goNext();
                        } else {
                            console.error(fetchRes)
                            onFail();
                        }
                    })
                    .catch((err) => {
                        console.error(err);
                        onFail();
                    })
            }

            post(0);
        }
    </script>
</body>

</html>