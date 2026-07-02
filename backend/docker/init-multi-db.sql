SELECT 'CREATE DATABASE wallet_sandbox OWNER wallet_user'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wallet_sandbox')\gexec

SELECT 'CREATE DATABASE wallet_testing OWNER wallet_user'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'wallet_testing')\gexec
