# Hold the now line at the paint's time.
s/const line = nowLine\(days, \(Number\(now\) \|\| 0\) \+ elapsed, look\.zone, plan\.window\);/const line = nowLine(days, Number(now) || 0, look.zone, plan.window);/;
