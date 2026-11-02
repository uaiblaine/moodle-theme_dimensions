# Serve the public calendar with the switch off.
s/            if \(visitor::enabled\(\)\) \{/            if (true) {/;
