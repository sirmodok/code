import os
from langchain.agents import AgentType, create_sql_agent, initialize_agent, load_tools
from langchain.memory import ConversationBufferMemory
from langchain.agents.agent_toolkits import SQLDatabaseToolkit
from langchain.chat_models import ChatOpenAI
from langchain.vectorstores import Chroma
from langchain.sql_database import SQLDatabase
from langchain.text_splitter import TokenTextSplitter
from langchain.embeddings import OpenAIEmbeddings
from langchain.tools import Tool

openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD

embeddings = OpenAIEmbeddings() # Creates an instance of the OpenAIEmbeddings object. This object converts large amounts of data to a vector store for easy relationshiping of data
embed_model = "text-embedding-ada-002" # Change to to change the *embedding* model

#Basic information about the database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history
llm = ChatOpenAI(temperature=0, model="gpt-4") # Create a new instance of the ChatOpenAI object. This is how we interact with OpenAI using a chatbot type format
sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}") # Create a langchain owned instance of our database
text_splitter = TokenTextSplitter(chunk_size = 2048, chunk_overlap = 20) # Creates an instance of a textSplitter object. This is used to attempt to break the text apart by context. This is called chunking
def query_sql_db():
    output = sql_agent_executer.run("SELECT * FROM assets")
    return output

sql_toolkit = SQLDatabaseToolkit(db=sql_db, llm=llm) # Create an instance of an sql database toolkit. This allows openai to interact directly with the SQL dataase
sql_agent_executer = create_sql_agent(llm=llm, toolkit=sql_toolkit, verbose=True) # Create an agent executer object that uses our Sql toolkit
tools = load_tools(["terminal"])
tools.append(Tool.from_function(func=query_sql_db, name="sql_run", description="run a query on an sql db"))
agent_chain = initialize_agent(tools=tools, llm=llm, agent=AgentType.CHAT_CONVERSATIONAL_REACT_DESCRIPTION, verbose=True, memory=memory)

full_list = [] # I just needed a list object here to use for the SQL information
tables = sql_db.get_usable_table_names() # Get a list of table names from our SQL database

# Iterate over those table names and for each table, get all the columns and rows
for i in tables:
    sql_query = sql_db.run_no_throw("""SELECT * FROM {}""".format(i))
    full_list.append(sql_query)

split_docs = text_splitter.create_documents(full_list) # Use our textsplitter object to chunk the text

db = Chroma.from_documents(split_docs, embeddings) # Take our chunked documents and use the embeddings object to create vectors of the data and store it into the Chroma vector database
similarity_search = db.similarity_search("How many assets are there?", k=1)


