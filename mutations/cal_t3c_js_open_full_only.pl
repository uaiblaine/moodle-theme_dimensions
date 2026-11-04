# Say "no end date set" of a step that has an end too.
s/step\.start > 0 && step\.end <= 0 \? look\.strings\.calendar_trailopenfull/step.start > 0 ? look.strings.calendar_trailopenfull/;
