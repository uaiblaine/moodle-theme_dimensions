# Drop the Rate button's id: tool_lp/grade_user_competency_inline binds #rate_<uniqid> and throws without it, so the
# primary action would be a button that opens nothing.
s/ id="rate_\{\{uniqid\}\}">\{\{#str\}\}rate, tool_lp/>{{#str}}rate, tool_lp/;
