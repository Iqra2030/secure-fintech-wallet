# Evidence capture sheet

Record the branch/commit, database reset state, server port, time and Burp request for every row. Expected outcomes are in BURP_GUIDE.md. Fill actual results only after running.

| Test | Before: status and state | After: status and state | Evidence filenames | Pass/fail |
|---|---|---|---|---|
| W1 Other wallet access | | | | |
| W2 Negative transfer | | | | |
| W3 Duplicate transfer | | | | |
| W4 Forged webhook | | | | |
| W4 Valid signed replay | | | | |
| W5 Login limit | | | | |
| W6 Forced partial failure | | | | |
| W6 Concurrent spending | | | | |
| Legitimate transfer still works | | | | |

A status code alone is insufficient: show balances and record counts. Redact session cookies, CSRF values and secrets in submitted screenshots when practical.
