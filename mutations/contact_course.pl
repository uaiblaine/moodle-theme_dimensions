# Make the contact test ignore which course was asked about, so any course id in the
# URL opens the profile of anybody who is a contact anywhere.
s/get_user_roles\(\$coursecontext, \$user->id, true\)/get_user_roles(\\core\\context\\system::instance(), \$user->id, true)/;
