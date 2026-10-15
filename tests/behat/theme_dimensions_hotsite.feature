@theme @theme_dimensions @theme_dimensions_hotsite
Feature: Course enrolment hotsite
  In order to publicise course offerings
  As a member of the course audience
  I need the enrolment hotsite to show what the course is and how to join it

  Background:
    Given the following config values are set as admin:
      | forcelogin         | 0 |                            |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |

  Scenario: A logged-in user sees the hotsite of a course with a page model
    Given the following "courses" exist:
      | fullname       | shortname | summary                  | customfield_hotsite_modelo | customfield_modalidade | customfield_carga_horaria | customfield_local_cidade |
      | Hotsite Course | HC1       | <p>About the course.</p> | 1                          | 2                      | 36                        | São Paulo                 |
    When I am on the "HC1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "Hotsite Course"
    And I should see "In-person"
    And I should see "36 hours"
    And I should see "São Paulo"
    And I should see "About the course."
    And I should see "Enrolments are unavailable at the moment"

  Scenario: A user enrolled from a later date is told when the access starts and is offered no enrolment
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Sue       | Student  | student2@example.com |
    And the following "courses" exist:
      | fullname         | shortname | customfield_hotsite_modelo | customfield_modalidade |
      | Scheduled Course | SC1       | 1                          | 1                      |
    And the following "course enrolments" exist:
      | user     | course | role    | timestart   |
      | student1 | SC1    | student | ##+3 days## |
    When I am on the "SC1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "Your access starts on"
    And I should see "You are enrolled in this course. It opens to you on that date."
    And I should not see "Enrolments are unavailable at the moment"
    And I should not see "Access the course"
    # The control: somebody with no enrolment gets the ordinary enrolment area of the same course.
    And I log out
    And I am on the "SC1" "theme_dimensions > Enrolment hotsite" page logged in as "student2"
    And I should not see "Your access starts on"
    And I should see "Enrolments are unavailable at the moment"

  Scenario: A user whose enrolment was suspended or has ended is told so on the hotsite
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Sue       | Student  | student2@example.com |
      | student3 | Tom       | Student  | student3@example.com |
    And the following "courses" exist:
      | fullname        | shortname | customfield_hotsite_modelo | customfield_modalidade |
      | Paused Course   | PC1       | 1                          | 1                      |
    And the following "course enrolments" exist:
      | user     | course | role    | status    | timestart    | timeend     |
      | student1 | PC1    | student | suspended | 0            | 0           |
      | student2 | PC1    | student | active    | ##-10 days## | ##-2 days## |
    When I am on the "PC1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "Your enrolment in this course is suspended."
    And I should not see "Your enrolment ended on"
    And I log out
    And I am on the "PC1" "theme_dimensions > Enrolment hotsite" page logged in as "student2"
    And I should see "Your enrolment ended on"
    And I should not see "Your enrolment in this course is suspended."
    # The control: somebody with no row reads neither note on the same course.
    And I log out
    And I am on the "PC1" "theme_dimensions > Enrolment hotsite" page logged in as "student3"
    And I should not see "Your enrolment ended on"
    And I should not see "Your enrolment in this course is suspended."
    And I should see "Enrolments are unavailable at the moment"

  Scenario: A visitor sees the hotsite of a public course while the site forces login
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname   | shortname | customfield_hotsite_modelo | customfield_descricao_publica |
      | Open Event | OE1       | 2                          | Curso aberto & gratuito       |
    And the course "OE1" is "public" for discoverability
    # A login leads somewhere only where a method would take the learner in.
    And I am on the "Open Event" "enrolment methods" page logged in as "admin"
    And I click on "Enable" "link" in the "Self enrolment" "table_row"
    And I log out
    When I am on the "OE1" "theme_dimensions > Enrolment hotsite" page
    Then I should see "Open Event"
    And I should see "Log in to enrol"
    And "//meta[@property='og:title'][@content='Open Event']" "xpath_element" should exist
    And "//meta[@property='og:description'][@content='Curso aberto & gratuito']" "xpath_element" should exist
    And "//meta[@name='robots'][@content='noindex']" "xpath_element" should exist
    And "//link[@rel='canonical']" "xpath_element" should exist
    And "//meta[@property='og:image']" "xpath_element" should not exist

  Scenario: A visitor of a course whose only way in is guest access is led in as a guest, not to the login
    Given the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo | customfield_modalidade |
      | Guest Course | GA1       | 1                          | 1                      |
    And the course "GA1" is "public" for discoverability
    And I am on the "Guest Course" "enrolment methods" page logged in as "admin"
    And I click on "Enable" "link" in the "Guest access" "table_row"
    And I log out
    When I am on the "GA1" "theme_dimensions > Enrolment hotsite" page
    Then I should see "Access as guest"
    And I should see "No enrolment needed: you browse the course as a guest."
    And I should see "Already enrolled? Log in"
    And I should not see "Log in to enrol"

  Scenario: A visitor enters a public course as a guest from its guest entry while the site forces login
    # Without JavaScript the interstitial waits for its button; the module only presses it sooner. The site
    # admits the guest account through the login page's guest button, or the entry is withdrawn; not through
    # auto-login of guests, which under forcelogin logs the visitor in as the guest on the front page that
    # "I log out" lands on, so the entry would send an already logged-in guest straight to the course.
    Given the following config values are set as admin:
      | forcelogin       | 1 |
      | guestloginbutton | 1 |
    And the following "courses" exist:
      | fullname       | shortname | customfield_hotsite_modelo | customfield_modalidade |
      | Visitor Course | VG1       | 1                          | 1                      |
    And the course "VG1" is "public" for discoverability
    And I am on the "Visitor Course" "enrolment methods" page logged in as "admin"
    And I click on "Enable" "link" in the "Guest access" "table_row"
    And I log out
    When I am on the "VG1" "theme_dimensions > Hotsite guest entry" page
    Then I should see "You are entering as a guest."
    And "//link[@rel='canonical']" "xpath_element" should exist
    And I press "Enter now"
    And I should see "You are currently using guest access"
    And I should see "Visitor Course"

  Scenario: A visitor sees the image tags of a public course that has a course image
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Imaged Event | IE1       | 2                          |
    And the course "IE1" has a course image
    And the course "IE1" is "public" for discoverability
    When I am on the "IE1" "theme_dimensions > Enrolment hotsite" page
    Then I should see "Imaged Event"
    And "//meta[@property='og:image'][contains(@content, '/theme_dimensions/ogimage/')]" "xpath_element" should exist
    And "//meta[@property='og:image:width'][@content='1200']" "xpath_element" should exist
    And "//meta[@property='og:image:height'][@content='630']" "xpath_element" should exist
    And "//div[contains(@class, 'dimensions-hotsite__hero-media')][contains(@style, '/theme_dimensions/hero/')]" "xpath_element" should exist

  Scenario: A visitor is sent to the login page for a course that is not public
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Closed Event | CE1       | 2                          |
    When I am on the "CE1" "theme_dimensions > Enrolment hotsite" page
    Then "Username" "field" should exist
    And I should not see "Closed Event"

  Scenario: A visitor is sent to the login page for a course that does not exist
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I visit "/theme/dimensions/hotsite.php?id=987654"
    Then "Username" "field" should exist

  Scenario: A logged-in user sees a public course's page without its head tags leaking anything else
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Public Curso | PU1       | 1                          |
    And the course "PU1" is "public" for discoverability
    When I am on the "PU1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "Public Curso"
    And "//meta[@property='og:title'][@content='Public Curso']" "xpath_element" should exist
    And "//meta[@property='og:description']" "xpath_element" should not exist

  Scenario: A course without a page model keeps the standard enrolment page
    Given the following "courses" exist:
      | fullname     | shortname |
      | Plain Course | PC1       |
    When I am on the "PC1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "You cannot enrol yourself in this course"

  Scenario: The standard enrolment page forwards to the hotsite when a model is set
    Given the following "courses" exist:
      | fullname       | shortname | customfield_hotsite_modelo | customfield_carga_horaria |
      | Forward Course | FC1       | 1                          | 36                        |
    When I am on the "FC1" "theme_dimensions > Standard enrolment page" page logged in as "student1"
    Then I should see "36 hours"
    And I should see "Enrolments are unavailable at the moment"
    And I should not see "You cannot enrol yourself in this course"

  Scenario: The standard enrolment page stays standard without a model
    Given the following "courses" exist:
      | fullname     | shortname |
      | Plain Course | PC2       |
    When I am on the "PC2" "theme_dimensions > Standard enrolment page" page logged in as "student1"
    Then I should see "You cannot enrol yourself in this course"

  Scenario: The course form offers the public description and no longer a public checkbox
    Given the following "courses" exist:
      | fullname     | shortname |
      | Gated Course | GC1       |
    When I am on the "GC1" "course editing" page logged in as "admin"
    Then I should see "Enrolment page model"
    And I should see "Public description"
    And I should not see "Public enrolment page"

  Scenario: A configured course offers its public address and a link to its hotsite from the course settings
    Given the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Linked Curso | LC1       | 1                          |
    When I am on the "LC1" "course editing" page logged in as "admin"
    Then "View the enrolment hotsite" "link" should exist
    And I should see "This is the public address to share."
    And I should see "Public address"
    And I should see the hotsite address of course "LC1"
    And "Copy address" "button" should exist

  Scenario: A course without a page model offers no link to a hotsite
    Given the following "courses" exist:
      | fullname       | shortname |
      | Unlinked Curso | LC2       |
    When I am on the "LC2" "course editing" page logged in as "admin"
    Then "View the enrolment hotsite" "link" should not exist

  Scenario: A past event shows the held seal instead of any enrolment action
    Given the following "courses" exist:
      | fullname   | shortname | startdate       | enddate        | customfield_hotsite_modelo | customfield_modalidade |
      | Past Event | PE1       | ##10 days ago## | ##3 days ago## | 2                          | 1                      |
    When I am on the "PE1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "Held on"
    And I should see "Materials and certificates are available to enrolled participants, inside the course."
    And "Enrol" "button" should not exist
    And "Enrol" "link" should not exist

  # The badge field is of customfield_picture's type, an optional plugin: a site without it excludes
  # this scenario with --tags '~@customfield_picture'.
  @javascript @_file_upload @customfield_picture
  Scenario: An editor uploads a badge and a video address and the hotsite shows both
    Given I enable "youtube" "media" plugin
    And the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Badge Course | BC1       | 1                          |
    When I am on the "BC1" "course editing" page logged in as "admin"
    And I click on "Expand all" "link" in the "region-main" "region"
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Badge 1" filemanager
    And I set the field "Presentation video (address)" to "https://www.youtube.com/watch?v=jNQXAC9IVRw"
    And I press "Save and display"
    And I am on the "BC1" "theme_dimensions > Enrolment hotsite" page
    Then "//ul[contains(@class, 'dimensions-hotsite__badges')]//img[@alt='gd logo']" "xpath_element" should exist
    And "//div[contains(@class, 'dimensions-hotsite__player')]//iframe[contains(@src, 'youtube')]" "xpath_element" should exist
    And I should see "Course presentation"

  Scenario: A video address no media player embeds is refused on the course form
    Given the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Video Course | VC1       | 1                          |
    When I am on the "VC1" "course editing" page logged in as "admin"
    And I set the field "Presentation video (address)" to "https://example.com/video.html"
    And I press "Save and display"
    Then I should see "No enabled media player embeds this address"

  @javascript
  Scenario: The course form shows the study format, the live classes and the hybrid fields only where they apply
    Given the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo |
      | Format Curso | FC1       | 1                          |
    When I am on the "FC1" "course editing" page logged in as "admin"
    And I click on "Expand all" "link" in the "region-main" "region"
    Then "customfield_encontros_presenciais" "field" should not be visible
    And I should not see "Hybrid offerings only: a short line about the in-person meetings"
    And I set the field "customfield_modalidade" to "Hybrid"
    And "customfield_encontros_presenciais" "field" should be visible
    And I should see "Hybrid offerings only: a short line about the in-person meetings"
    And I set the field "customfield_formato" to "Self-paced"
    And "id_customfield_aulas_aovivo" "field" should not be visible
    And I set the field "customfield_formato" to "Tutored"
    And "id_customfield_aulas_aovivo" "field" should be visible
    And "customfield_aulas_aovivo_quando" "field" should not be visible
    And I set the field "id_customfield_aulas_aovivo" to "1"
    And "customfield_aulas_aovivo_quando" "field" should be visible
    And I set the field "customfield_hotsite_modelo" to "Event"
    And "customfield_formato" "field" should not be visible
    And "id_customfield_aulas_aovivo" "field" should not be visible
    And I should not see "Courses only: whether participants study alone"

  Scenario: A self enrolment with a cap shows its seats on the hotsite
    Given the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo | customfield_modalidade |
      | Seats Course | SC1       | 1                          | 1                      |
    And I log in as "admin"
    And I add "Self enrolment" enrolment method in "Seats Course" with:
      | Custom instance name | Vagas |
      | Max enrolled users   | 40    |
    And I log out
    When I am on the "SC1" "theme_dimensions > Enrolment hotsite" page logged in as "student1"
    Then I should see "40 seats"
    And "Enrol" "link" should exist

  Scenario: An event whose enrolment method sets no deadline is warned on its settings
    Given the following "courses" exist:
      | fullname      | shortname | customfield_hotsite_modelo |
      | Undated Event | UE1       | 2                          |
    And I log in as "admin"
    And I add "Self enrolment" enrolment method in "Undated Event" with:
      | Custom instance name | Ao vivo |
    When I am on the "UE1" "course editing" page
    Then I should see "no enrolment end date is set on Ao vivo"

  Scenario: A self-paced course that is not online is refused on the course form
    Given the following "courses" exist:
      | fullname        | shortname | customfield_hotsite_modelo |
      | Self-paced Mix  | SP1       | 1                          |
    When I am on the "SP1" "course editing" page logged in as "admin"
    And I set the following fields to these values:
      | customfield_modalidade | Hybrid     |
      | customfield_formato    | Self-paced |
    And I press "Save and display"
    Then I should see "A self-paced course is always online."

  Scenario: The public address opens the hotsite
    Given the following "courses" exist:
      | fullname      | shortname | customfield_hotsite_modelo |
      | Shared Course | SC1       | 1                          |
    When I am on the "SC1" "theme_dimensions > Hotsite short address" page logged in as "student1"
    Then I should see "Shared Course"
    And I should see "Enrolments are unavailable at the moment"

  Scenario: The public address of a course that is not public sends a visitor to the login page
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname      | shortname | customfield_hotsite_modelo |
      | Closed Course | CC1       | 1                          |
    When I am on the "CC1" "theme_dimensions > Hotsite short address" page
    Then "Username" "field" should exist
    And I should not see "Closed Course"

  Scenario: The old address of the hotsite keeps answering, on the page's canonical address
    Given the following "courses" exist:
      | fullname      | shortname | customfield_hotsite_modelo |
      | Moved Course  | MC1       | 1                          |
    When I am on the "MC1" "theme_dimensions > Legacy hotsite address" page logged in as "student1"
    Then I should see "Moved Course"
    And the browser is on the hotsite of course "MC1"

  Scenario: The routed address of the hotsite answers through r.php on any web server
    Given the following "courses" exist:
      | fullname      | shortname | customfield_hotsite_modelo |
      | Routed Course | RC1       | 1                          |
    When I am on the "RC1" "theme_dimensions > Routed hotsite address" page logged in as "student1"
    Then I should see "Routed Course"
    And I should see "Enrolments are unavailable at the moment"

  Scenario: A visitor reaches the routed address of a public course while the site forces login
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "courses" exist:
      | fullname     | shortname | customfield_hotsite_modelo | customfield_descricao_publica |
      | Routed Event | RE1       | 2                          | Evento aberto                 |
    And the course "RE1" is "public" for discoverability
    # A login leads somewhere only where a method would take the learner in.
    And I am on the "Routed Event" "enrolment methods" page logged in as "admin"
    And I click on "Enable" "link" in the "Self enrolment" "table_row"
    And I log out
    When I am on the "RE1" "theme_dimensions > Routed hotsite address" page
    Then I should see "Routed Event"
    And I should see "Log in to enrol"
    And "//meta[@property='og:description'][@content='Evento aberto']" "xpath_element" should exist
    And "//link[@rel='canonical']" "xpath_element" should exist
