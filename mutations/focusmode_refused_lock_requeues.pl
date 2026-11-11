# Drop the queued task when sync_default() cannot take the provisioning lock, the old silent return: the setting is saved and the field never follows.
s/(The setting is saved already[^\n]*\n\s*)self::queue_reconcile\(\);/$1/;
