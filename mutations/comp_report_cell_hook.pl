# Drop the rating cell's data-user-competency, leaving the selector the grading popup is armed with:
# report_competency/grading_popup listens on that attribute, so no rating cell would open the dialogue.
s/\n\s*data-user-competency="true"//;
