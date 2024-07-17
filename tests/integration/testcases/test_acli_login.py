import unittest
import subprocess
import threading
import time
import json

unittest.TestLoader.sortTestMethodsUsing = None

class TestExecutableWithPrompt(unittest.TestCase):
    application_uuid = "2ed281d4-9dec-4cc3-ac63-691c3ba002c2"
    environment_name = "automated_tests_"+str(time.time()).split(".")[0]
    branch = "master"
    application_name = "pipelinesvalidation2"

    def run_executable(self, inputs, params=None, numeric_input=None):
        output_list = []
        params = params if params is not None else []
        numeric_input = numeric_input if numeric_input is not None else []

        # Ensure numeric_input is a list of strings
        if not isinstance(numeric_input, list):
            numeric_input = [str(numeric_input)]

        else:
            numeric_input = [str(number) for number in numeric_input]

        command = ['acli'] + params
        process = subprocess.Popen(command,
                                   stdin=subprocess.PIPE,
                                   stdout=subprocess.PIPE,
                                   stderr=subprocess.PIPE,
                                   text=True,
                                   bufsize=1)

        # Function to feed input to the executable
        def write_input(proc_stdin, lines):

            for line in lines:
                proc_stdin.write(line + '\n')
                proc_stdin.flush()

        # input_lines = inputs + numeric_input
        # # Start a thread to write input to avoid blocking

        # Read output lines
        with process.stdout:
            for line in iter(process.stdout.readline, ''):
                print(line, end='')
                output_list.append(line.replace('\n','').strip())
                if('Enter a new API key' in line):
                    input_thread = threading.Thread(target=write_input, args=(process.stdin, ["Enter a new API key"]))
                    input_thread.start()

        # Wait for the thread to finish, before closing stdin and stdout
        try:
          input_thread.join()
        except UnboundLocalError as unboundLocalError:
          print("UnboundLocalError: " + str(unboundLocalError))

        # Process is now finished, we can read the rest of stdout and stderr
        stdout, stderr = process.communicate()

        return process.returncode, stdout, stderr, output_list

    def test_00_executable_number_prompt(self):
        user_inputs = ['0','0','0','0','0','0','0','3']
        numeric_input = [3]
        parameters = ['auth:login','--key','xxx','--secret','xxx']

        return_code, stdout, stderr, output_list = self.run_executable(user_inputs,
                                                          params=parameters,
                                                          numeric_input=numeric_input)

        # Assertions to verify the behavior
        print("stdout is: " + stdout)
        print(stderr)
        print(output_list)
        self.assertEqual(return_code, 0)
        self.assertTrue("Saved credentials" in output_list, "Saved credentials not found in output")

    def test_01_auth_login_with_telemetry_disabled(self):
        parameters = ['telemetry:disable']
        user_inputs = None
        return_code, stdout, stderr, output_list = self.run_executable(user_inputs,
                                                          params=parameters)

        # Assertions to verify the behavior
        print("stdout is: " + stdout)
        print(stderr)
        print(output_list)
        self.assertEqual(return_code, 0)
        self.assertTrue("[OK] Telemetry has been disabled." in output_list, "Telemetry disabled not found in output")

    def test_02_environment_create(self):
        '''
        First we will `initiate the environment create command and get notification uuid from the output
        Verify the notification uuid is valid and verify that status of environment is in-progress
        Get the environment uuid by getting environments in the application and validating name that is provided while environment created, also validate status is normal
        '''

        parameters = ["api:applications:environment-create",self.application_uuid, self.environment_name, self.branch, self.application_name]

        user_inputs = None
        return_code, stdout, stderr, output_list = self.run_executable(user_inputs,
                                                          params=parameters)

        print("stdout is: " + stdout)
        print(stderr)
        print("output list")
        print(output_list)
        global notification_id
        notification_id = output_list["_links"]['notification']['href'].split("notifications\\/")[1]
        self.assertEqual(return_code, 0)
        self.assertTrue("Adding an environment" in output_list, "Adding environment not found in output")

    def test_03_notifications(self):

        # Verifying notification
        parameters = ["api:notifications:find", self.notification_id]

        user_inputs = None
        return_code, stdout, stderr, output_list = self.run_executable(user_inputs,
                                                          params=parameters)

        print("stdout is: " + stdout)
        print(stderr)
        print("output list")
        print(output_list)
        print(type(output_list))

        json_string = ''.join(output_list)
        json_object = json.loads(json_string)

        self.assertEqual(return_code, 0)
        self.assertEqual(json_object.get('event'),"EnvironmentAdded")
        assert json_object.get("status") in ["in-progress", "completed"]


        # Getting environment id
        parameters_2 = ["api:applications:environment-list",self.application_uuid]
        user_inputs_2 = None
        return_code_2, stdout_2, stderr_2, output_list_2 = self.run_executable(user_inputs_2,
                                                          params=parameters_2)

        print("stdout is: " + stdout_2)
        print(stderr_2)
        print("output list")
        print(output_list_2)
        self.assertEqual(return_code, 0)

        json_string_2 = ''.join(output_list_2)
        json_object_2 = json.loads(json_string_2)

        #Search for the label and Extract the ID
        for item in json_object_2:
            if item.get('label') == self.environment_name:
                print(f"ID of sublist where label is {self.environment_name}: {item.get('id')}")
                global environment_id
                environment_id = item.get('id')
                print(environment_id)
                assert item.get('status')=="normal", "expected normal received {item.get('status'}"
                break

    def test_05_environment_delete(self):
        # Verify environment is deleted
        parameters = ["api:environments:delete", environment_id]
        user_inputs = None
        return_code, stdout, stderr, output_list = self.run_executable(user_inputs,
                                                          params=parameters)

        print("stdout is: " + stdout)
        print(stderr)
        print("output list")
        print(output_list)
        self.assertEqual(return_code, 0)
        message = '"message": "The environment is being deleted.",'
        self.assertTrue(message in output_list, "Environment is being deleted not found in output")


# Additional test methods and test cases...

if __name__ == '__main__':
    unittest.main()
