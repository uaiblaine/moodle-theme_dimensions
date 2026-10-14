# Fill the remembered state into an address that names part of the state: a shared link then
# means something else to each reader who opens it.
s/        if \(\$named\) \{/        if (false) {/;
