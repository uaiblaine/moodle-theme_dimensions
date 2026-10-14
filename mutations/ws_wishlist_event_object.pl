# Name the FAVOURITE the write created as the event's object, instead of the course it is about.
# The row is still written, the answer is still right and every assertion made off a redirectEvents()
# sink about the event firing at all stays green - what changes is the one column an administrator
# reads the report by, and core's own service docblock calls out this exact confusion ("the id of
# the item which was favourited (not the favourite's id)"). Only a test that reads the STORED row
# back out of a logstore, column by column, can see it.
s/            'objectid' => \$courseid,/            'objectid' => (int) \$service->get_favourite(self::FAVOURITE_COMPONENT, self::FAVOURITE_ITEMTYPE, \$courseid, \$coursecontext)?->id,/;
