# Point "Create my account" at the password-recovery page instead of the sign-up page.
s/href="\{\{signupurl\}\}">\{\{#str\}\} login_signup/href="{{forgotpasswordurl}}">{{#str}} login_signup/;
