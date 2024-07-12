# Undergraduate Course Substitution Request

An application which allows College of Arts & Humanities Advisors to manage course substitutions for individual students. Also supports editing requests, as well as sending requests back to the originator in order to request edits.

## Installation

To set up this application, follow these steps:

* Clone this repository into your web server's document root
* Install the required composer packages with `$ composer install`
* Make a copy of `.env.example`, rename it to `.env`, and fill in the various values for your environment
* Ensure there is a directory named `athena` in the application root which contains the contents of the `dist` directory from the [UCF Athena Framework's Github repository](https://github.com/UCF/Athena-Framework/). For my part, since the framework is a common one in our environments, I stored the directory elsewhere on the server and created a symbolic link to it in the application root.