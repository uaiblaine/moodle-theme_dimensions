# Name core's own favourite service in the listing's account module, a file of the ES client the star module does not own.
s/^import type \{Rows, Shell\} from '\.\/store';$/import type {Rows, Shell} from '.\/store';\nconst FAVOURITE_SERVICE = 'core_course_set_favourite_courses';/m;
