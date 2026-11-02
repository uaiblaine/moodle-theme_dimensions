# Serve the calendar page to a visitor even with the public calendar off, instead of sending them to log in.
s/            if \(visitor::enabled\(\)\) \{/            if (true) {/;
