# Arm the client modules for the guest account too. The guest is routed to this shell on purpose
# and all three of the showcase's web services refuse it, so an armed module there hides the
# no-JavaScript "Filter" submit and the numbered pager - the only controls a guest HAS - and
# answers every click with an exception dialogue; the panel module, armed at the root as well,
# would turn every poster into a button whose panel can never load, and the listing's, armed at
# the root too, would make its search line ask a service that refuses it.
s/if \(isloggedin\(\) && !isguestuser\(\)\) \{/if (true) {/;
