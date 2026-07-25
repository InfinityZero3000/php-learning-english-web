FROM redis:8-alpine

CMD ["sh", "-c", "exec redis-server --appendonly yes --appendfsync everysec --requirepass \"$REDIS_PASSWORD\""]
