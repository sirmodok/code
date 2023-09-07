import os
from langchain.agents import initialize_agent, load_tools, AgentType
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.memory import ConversationBufferMemory
from langchain.agents.agent_toolkits import SQLDatabaseToolkit
from langchain.prompts import MessagesPlaceholder
from langchain.schema import SystemMessage

# Define a class SimpleBot
class SimpleBot():
    # Initialize the bot
    def __init__(self):
        # Get environment variables for OpenAI API key and SQL DB password
        self.openai_api_key = os.getenv("OPENAI_API_KEY") 
        self.sql_db_password = os.getenv("SQL_DB_PASSWORD")
        
        # Define database user, host, and name
        self.db_user = "simplerisk"
        self.db_host = "localhost"
        self.db_name = "simplerisk"
        
        # Initialize SQL database
        self.sql_db = self.initialize_sql_db()
        
        # Initialize toolkit with SQL database and OpenAI chat model
        self.toolkit = SQLDatabaseToolkit(db=self.sql_db, llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-16k-0613", verbose=True))
        
        # Get tools from toolkit and extend with additional tools
        self.tools = self.toolkit.get_tools()
        self.tools.extend(load_tools(["serpapi", "terminal", "human"]))
        
        # Initialize agent
        self.initialize_agent()

    # Function to initialize SQL database
    def initialize_sql_db(self):
        try:
            # Return SQL database object
            return SQLDatabase.from_uri(f"mysql+pymysql://{self.db_user}:{self.sql_db_password}@{self.db_host}/{self.db_name}")
        except Exception as e:
            print(f"Failed to initialize SQL Database: {e}")
            return None

    # Function to initialize agent
    def initialize_agent(self):
        # Define content for system message
        content = "You are in charge of a database named simplerisk that contains an enterprise companies risk data. "
        
        # Define arguments for agent initialization
        agent_kwargs = {"extra_prompt_messages": [MessagesPlaceholder(variable_name="memory")], "system_message": SystemMessage(content=content)}
        
        # Define memory for conversation
        memory = ConversationBufferMemory(memory_key="memory", return_messages=True)
        
        # Initialize agent with tools, OpenAI chat model, agent type, verbosity, agent arguments, and memory
        self.agent = initialize_agent(self.tools, llm=ChatOpenAI(temperature=0.5, model="gpt-3.5-turbo-16k-0613", verbose=True), agent=AgentType.OPENAI_FUNCTIONS, verbose=True, agent_kwargs=agent_kwargs, memory=memory)
