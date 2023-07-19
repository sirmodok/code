import os
from langchain.agents import AgentType, create_sql_agent, initialize_agent, load_tools
from langchain.memory import ConversationBufferMemory
from langchain.agents.agent_toolkits import SQLDatabaseToolkit
from langchain.chat_models import ChatOpenAI
from langchain.vectorstores import Chroma
from langchain.sql_database import SQLDatabase
from langchain.text_splitter import TokenTextSplitter
from langchain.embeddings import OpenAIEmbeddings
from langchain.tools import StructuredTool


openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD



#Basic information about the database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

class SimpleAgent:
    def __init__(self):
        self.sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}") # Create a langchain owned object of our database
        self.llm = ChatOpenAI(temperature=0, model="gpt-4") # Create a new instance of the ChatOpenAI object. This is how we interact with OpenAI using a chatbot type format
        self.sql_toolkit = SQLDatabaseToolkit(db=self.sql_db, llm=self.llm) # Create an instance of an sql database toolkit. This allows openai to interact directly with the SQL dataase
        self.memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history
        self.tools = load_tools(["terminal"]) # Load up any built in tools needed for the agent
        self.tools.append(StructuredTool.from_function(func=self.query_sql_db, name="sql_run", description="run a query on an sql db")) # Add the custom tool that uses the executer we defined earlier.
        self.sql_agent_executer = create_sql_agent(llm=self.llm, toolkit=self.sql_toolkit, verbose=True) # Create an agent executer object that uses our Sql toolkit
        self.embeddings = OpenAIEmbeddings(model="text-embedding-ada-002") # Creates an instance of the OpenAIEmbeddings object. This object converts large amounts of data to a vector store for easy relationshiping of data
        self.text_splitter = TokenTextSplitter(chunk_size = 2048, chunk_overlap = 20) # Creates an instance of a textSplitter object. This is used to attempt to break the text apart by context. This is called chunking
        self.agent_chain = initialize_agent(tools=self.tools, llm=self.llm, agent=AgentType.STRUCTURED_CHAT_ZERO_SHOT_REACT_DESCRIPTION, verbose=True, memory=self.memory) # Create an agent object that uses all of our previously defined objects.

    def query_sql_db(sql_agent_executer):
        output = sql_agent_executer.run("SELECT * FROM assets")
        return output


if __name__ == "__main__":
    print("start")
    the_agent = SimpleAgent()
    print("agent created")
    the_agent.agent_chain("sql query to list all tables")













    # # Iterate over those table names and for each table, get all the columns and rows
    # for i in tables:
    #     sql_query = the_agent.sql_db.run_no_throw("""SELECT * FROM {}""".format(i))
    #     full_list.append(sql_query)
    # split_docs = the_agent.text_splitter.create_documents(full_list) # Use our textsplitter object to chunk the text
    # ids = [str(i) for i in range(1, len(split_docs) + 1)]
    # db = Chroma.from_documents(split_docs, SimpleAgent.embeddings, persist_directory="./chroma_db", ids=ids) # Take our chunked documents and use the embeddings object to create vectors of the data and store it into the Chroma vector database stored locally