# Ask for every eager cover first instead of the first: they share the front of the queue.
s/\$card->priority = true;\n\s*return;/\$card->priority = true;/;
