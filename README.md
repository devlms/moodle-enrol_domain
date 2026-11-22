# Email domain self enrolment (enrol_domain)

Let people enrol themselves in a course only when their email address is at one of the domains you list, for example `school.edu`. Addresses at subdomains (`students.school.edu`) count too.

- Add it to a course: Participants > Enrolment methods > Add method > Email domain self enrolment, then list the domains.
- People at those domains see an "Enrol me" button. Everyone else sees which domains are allowed.
- Choose the role it gives (student by default). Teachers and managers can configure it (enrol/domain:config).
- Enable the plugin first: Site administration > Plugins > Enrolments > Manage enrol plugins.

The check uses the email address on the Moodle account, so it is only as strong as your email checks: keep email-based self-registration (it confirms new addresses) and "Email change confirmation" (Site administration > Security > Site security settings, on by default) so nobody can type in an address they do not own.

Moodle 4.5 to 5.3. Built by DevLMS: https://devlms.com
