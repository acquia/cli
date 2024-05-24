import unittest
import subprocess
import threading

class TestExecutableWithPrompt(unittest.TestCase):
    def run_executable(self, inputs, params=None, numeric_input=None):
        output_list = []
        params = params if params is not None else []
        numeric_input = numeric_input if numeric_input is not None else []

        # Ensure numeric_input is a list of strings
        if not isinstance(numeric_input, list):
            numeric_input = [str(numeric_input)]
        else:
            numeric_input = [str(number) for number in numeric_input]

        command = ['/usr/local/bin/acli'] + params
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

    def test_executable_number_prompt(self):
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

    def test_auth_login_with_telemetry_disabled(self):
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


# Additional test methods and test cases...

if __name__ == '__main__':
    unittest.main()
