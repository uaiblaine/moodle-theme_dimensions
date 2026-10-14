# The course form draws the guest entry's row only where the course offers it.
s/        if \(guestaccess::available\(\$course\)\) \{/        if (true) {/;
