Asterisk -> Telegram call notifications bot

Overview
- Listens to Asterisk AMI events
- Sends Telegram messages about incoming, answered, and missed calls
- No external Python dependencies (uses stdlib)

Requirements
- Python 3.9+
- Access to Asterisk AMI (host, port, username, secret)
- Telegram Bot Token and Chat ID

Install on CentOS 9 / FreePBX
1) Create bot folder (e.g. /opt/asterisk_telegram_bot) and copy files
2) Create environment file at /etc/sysconfig/asterisk-telegram-bot (see .env.example)
3) Create systemd unit file /etc/systemd/system/asterisk-telegram-bot.service (see systemd unit template)
4) systemctl daemon-reload && systemctl enable --now asterisk-telegram-bot

Configuration (.env example)
- TELEGRAM_BOT_TOKEN=123:ABC
- TELEGRAM_CHAT_ID=123456
- AMI_HOST=127.0.0.1
- AMI_PORT=5038
- AMI_USERNAME=telegram
- AMI_SECRET=secret
- CALLFLOW_INCLUDE_INTERNAL=true  # notify about internal-to-internal
- NOTIFY_MISSED=true
- SERVICE_NAME=asterisk-telegram-bot

Notes
- The bot correlates AMI events by Uniqueid and reports concise notifications
- Tested against Asterisk 18+ and FreePBX 16
- For FreePBX, create AMI user in /etc/asterisk/manager_additional.conf and apply config
