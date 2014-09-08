#!/bin/bash
#
# Cloud Hook: drupal-tests
#
# Run Drupal simpletests in the target environment using drush test-run.

site="$1"
target_env="$2"

# Select the tests to run. Run "drush help test-run" for options.
TESTS="UserRegistrationTestCase"

# Enable the simpletest module if it is not already enabled.
drush @$site.$target_env pm-enable simpletest --yes

# Run the tests.
drush @$site.$target_env test-run $TESTS
status=$?

# If we enabled simpletest, disable it.
drush @$site.$target_env pm-disable simpletest --yes

exit $status
