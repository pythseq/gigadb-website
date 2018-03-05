@issue-57 @user-claims-dataset
Feature: a user can claim his/her datasets
	As a gigadb user,
	I want to claim datasets that I've authored
	So I can manage them

Background:
	Given Gigadb web site is loaded with "gigadb_testdata.sql" data
	And user "joy_fox" is loaded

@ok
Scenario: Give users a button to claim a dataset they have authored
	Given I sign in as a user
	When I am on "/dataset/100002"
	Then I should see "Are you an author of this dataset? claim your dataset"

@ok
Scenario: Non logged-in visitors should not see the button
	Given I am not logged in to Gigadb web site
	When I am on "/dataset/100002"
	Then I should not see "Are you an author of this dataset? claim your dataset"


@ok
Scenario: a user is shown a modal to claim his/her dataset by reconcilling his/her author identity to his/her account
	Given I sign in as a user
	And I am on "/dataset/100002"
	When I follow "Are you an author of this dataset? claim your dataset now"
	Then the response should contain "Select your name"
	And the response should contain "Lambert DM"
	And the response should contain "Wang J"
	And the response should contain "Zhang G"
	And the response should contain "Claim selected author"

@wip @javascript
Scenario: a user select an author to claim and submit the claim form
	Given I sign in as a user
	And I am on "/dataset/100002"
	When I follow "Are you an author of this dataset? claim your dataset now"
	And I check the "Zhang G" radio button
	And I follow "Claim selected author"
	And I wait "5" seconds
	Then I should see "Your claim has been submitted to the administrators."



Scenario: a user with a pending claim is tryng to claim an author again
	Given I sign in as a user
	And I have elected to reconcile author "Zhang G" to my gigadb account
	When I press "Claim selected author"
	Then the response should contain "Your claim has been submitted to the administrators."


Scenario: a user already associated to an author cannot claim another author
	Given author "3794" is associated with default user
	When I sign in as default user
    And I go to "/dataset/100002"
	Then I should not see "Are you an author of this dataset? claim your dataset"
