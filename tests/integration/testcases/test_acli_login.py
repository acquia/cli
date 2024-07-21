import unittest
import subprocess
import threading
import time
import json
import os

unittest.TestLoader.sortTestMethodsUsing = None

class TestExecutableWithPrompt(unittest.TestCase):

    application_uuid = "2ed281d4-9dec-4cc3-ac63-691c3ba002c2"
    environment_name = "automated_tests_"+str(time.time()).split(".")[0]
    branch = "master"
    application_name = "pipelinesvalidation2"
    acli_auth_token = os.environ.get("ACLI_AUTH_TOKEN", "xxx")
    acli_auth_secret = os.environ.get("ACLI_AUTH_SECRET", "xxx")

    def run_executable(self, params=None):
        ''' Run the acli executable with the given parameters and returns the return code, stdout, stderr and output list'''
        output_list = []
        params = params if params is not None else []

        command = ['acli'] + params
        process = subprocess.Popen(command,
                                   stdin=subprocess.PIPE,
                                   stdout=subprocess.PIPE,
                                   stderr=subprocess.PIPE,
                                   text=True,
                                   bufsize=1)

        # Read output lines
        with process.stdout:
            for line in iter(process.stdout.readline, ''):
                print(line, end='')
                output_list.append(line.replace('\n','').strip())
                if('Would you like to share anonymous performance usage and data' in line):
                    process.stdin.write('yes\n')
                    process.stdin.flush()

        # Process is now finished, we can read the rest of stdout and stderr
        stdout, stderr = process.communicate()

        return process.returncode, stdout, stderr, output_list

    def auth_login(self):
        ''' Login with auth token and secret '''
        parameters = ['auth:login', '--key', self.acli_auth_token, '--secret', self.acli_auth_secret]

        return_code, stdout, stderr, output_list = self.run_executable(params=parameters)

        # Assertions to verify the behavior
        self.assertEqual(return_code, 0, stderr)
        self.assertTrue("Saved credentials" in output_list, output_list)

    def test_01_auth_login_with_telemetry_disabled(self):
        ''' Disable telemetry and login with auth token and secret '''
        parameters = ['telemetry:disable']
        return_code, stdout, stderr, output_list = self.run_executable(params=parameters)

        # Assertions to verify the behavior
        print(stderr)
        self.assertEqual(return_code, 0, stderr)
        self.assertTrue("[OK] Telemetry has been disabled." in output_list, output_list)

        self.auth_login()

    def test_02_environment_create(self):
        '''
        First we will `initiate the environment create command and get notification uuid from the output
        Verify the notification uuid is valid and verify that status of environment is in-progress
        Get the environment uuid by getting environments in the application and validating name that is provided while environment created, also validate status is normal
        '''

        environment_create_parameters = ["api:applications:environment-create",self.application_uuid, self.environment_name, self.branch, self.application_name]

        environment_create_return_code, stdout, environment_create_stderr, environment_create_output_list = self.run_executable(params=environment_create_parameters)

        environment_create_output_string = ''.join(environment_create_output_list)
        environment_create_output_json = json.loads(environment_create_output_string)

        global notification_id
        notification_id = environment_create_output_json["_links"]['notification']['href'].split("notifications/")[1]
        self.assertEqual(environment_create_return_code, 0, environment_create_stderr)
        self.assertTrue('"message": "Adding an environment.",' in environment_create_output_list, environment_create_output_list)

    def test_03_notifications(self):
        ''' Get the notification details using the notification id from the previous test case '''
        
        notification_parameters = ["api:notifications:find", notification_id]

        notification_return_code, stdout, notification_stderr, notification_output_list = self.run_executable(params=notification_parameters)

        notification_output_string = ''.join(notification_output_list)
        notification_output_json = json.loads(notification_output_string)

        self.assertEqual(notification_return_code, 0, notification_stderr)
        self.assertEqual(notification_output_json['event'],"EnvironmentAdded", notification_output_json)
        self.assertTrue(notification_output_json["status"] in ["in-progress", "completed"], notification_output_json)

        # Getting environment id
        environment_list_parameters = ["api:applications:environment-list",self.application_uuid]
        environment_list_return_code, stdout_2, environment_list_stderr, environment_list_output_list = self.run_executable(params=environment_list_parameters)

        self.assertEqual(environment_list_return_code, 0, environment_list_stderr)

        environment_list_json_string = ''.join(environment_list_output_list)
        environment_list_json_object = json.loads(environment_list_json_string)

        #Search for the label and Extract the ID
        for item in environment_list_json_object:
            if item.get('label') == self.environment_name:
                print(f"ID of sublist where label is {self.environment_name}: {item['id']}")
                global environment_id
                environment_id = item['id']
                print(environment_id)
                self.assertTrue(item['status'] in ["normal", "launching"], item)
                break

    def test_04_environment_delete(self):
        ''' Delete the environment created in the previous test case '''
        parameters = ["api:environments:delete", environment_id]
        return_code, stdout, stderr, output_list = self.run_executable(params=parameters)

        self.assertEqual(return_code, 0, stderr)
        message = '"message": "The environment is being deleted.",'
        self.assertTrue(message in output_list, output_list)

if __name__ == '__main__':
    unittest.main()
