# Drop the root's data-region-id: tool_lp/user_competency_info replaces the element it names after a rating, so the
# page would keep the old rating on screen.
s/ data-region-id="\{\{uniqid\}\}">\n<div data-region="competency-summary">/>\n<div data-region="competency-summary">/;
