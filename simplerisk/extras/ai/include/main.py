# Import necessary modules from flask and flaskwebgui
from flask import Flask, request, render_template
from flaskwebgui import FlaskUI
# Import the SimpleBot class from the simple module
import simple

# Define a class WebApp
class WebApp:
    # Initialize the web app
    def __init__(self):
        # Define the path to the browser executable
        self.browser_path = "/snap/bin/chromium"
        # Initialize a Flask app
        self.app = Flask(__name__)
        # Initialize a FlaskUI instance with the Flask app, port number, server type, window dimensions, and browser path
        self.ui = FlaskUI(app=self.app, port=5000, server="flask", width=800, height=900, browser_path=self.browser_path) 
        # Initialize a SimpleBot instance
        self.bot = simple.SimpleBot()

        # Define the route for the index page
        self.app.route("/", methods=["GET", "POST"])(self.index)
        # Define the route for the api page
        self.app.route("/api", methods=["GET", "POST"])(self.api)

    # Define the function to be called when the index page is accessed
    def index(self):
        # If the request method is POST
        airesponse = "error"
        if request.method == "POST":
            # Get the prompt from the form data
            prompt =  request.form["prompt"]
            # Get the AI's response to the prompt
            try:
                airesponse = self.bot.agent.run(prompt)
            except:
                return{"airesponse": "error with ai response"}
            # Return the AI's response as a dictionary
            return {"airesponse": airesponse}
        # If the request method is GET, render the index page
        return render_template("index.html")
    
    def api(self):
        airesponse = "error"
        # If the request method is POST
        if request.method == "POST":
            # Get the prompt from the form data
            prompt =   request.args['prompt']
            # Get the AI's response to the prompt
            try:
                airesponse = self.bot.agent.run(prompt)
            except:
                return{"airesponse": "error with ai response"}
            # Return the AI's response as a dictionary
            return {"airesponse": airesponse}
        # If the request method is GET, render the index page
        return airesponse

    # Define the function to run the web app
    def run(self):
        print("STARTING")
        # Run the FlaskUI instance
        self.ui.run()

# If this script is run directly (not imported as a module)
if __name__ == '__main__':
    # Initialize a WebApp instance
    web_app = WebApp()
    # Run the WebApp instance
    web_app.run()
