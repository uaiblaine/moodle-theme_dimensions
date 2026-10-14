# Validate the COURSE context instead of the category context the guard derives from it.
# validate_context() ends in require_login($course, ...), so every viewer without an enrolment
# in that course is refused - the ordinary case of the whole showcase - and with an exception
# that is not even the one refusal (requireloginerror).
s/external_api::validate_context\(\$context->get_parent_context\(\)\);/external_api::validate_context(\$context);/;
