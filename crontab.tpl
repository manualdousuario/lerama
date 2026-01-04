# Process feeds
${CRONTAB_PROCESS_FEEDS} /usr/local/bin/php /app/bin/lerama feed:process 2>&1 | tee -a /tmp/feed_process.log

# Check feed status
${CRONTAB_FEED_STATUS} /usr/local/bin/php /app/bin/lerama feed:check-status 2>&1 | tee -a /tmp/check_status.log

# Update proxy list
${CRONTAB_PROXY} /usr/local/bin/php /app/bin/lerama proxy:update 2>&1 | tee -a /tmp/proxy_update.log
