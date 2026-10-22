# Keep the colour mode menu in the phone navbar beside its twin in the drawer.
s/    body:has\(\.dimensions-drawer-modes\) #usernavigation \{/    body:has(.dimensions-nothing) #usernavigation {/;
