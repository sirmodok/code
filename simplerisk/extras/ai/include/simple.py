import os
from time import sleep
from langchain.agents import initialize_agent, create_sql_agent
from langchain.agents import tool
from langchain.agents import load_tools
from langchain.memory import ConversationBufferMemory
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.agents.agent_toolkits import SQLDatabaseToolkit
from langchain.agents import AgentType
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import OpenAIEmbeddings
from langchain.vectorstores import Chroma

openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD
text_splitter = RecursiveCharacterTextSplitter.from_tiktoken_encoder(chunk_size=12000, chunk_overlap=20, add_start_index=True)
embeddings = OpenAIEmbeddings(show_progress_bar=True)
vectorstore = Chroma(persist_directory='./.chroma', embedding_function=embeddings)
memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history
llm = ChatOpenAI(temperature=0.9, model="gpt-3.5-turbo-16k-0613", tiktoken_model_name="cl100k_base")

#Basic information about the simplrisk database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"
sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}")
tools = load_tools(["terminal"]) # Load up any built in tools needed for the agent


toolkit = SQLDatabaseToolkit(db=sql_db, llm=llm)

class SimpleBot():
    openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
    sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD
    text_splitter = RecursiveCharacterTextSplitter.from_tiktoken_encoder(chunk_size=12000, chunk_overlap=20, add_start_index=True)
    embeddings = OpenAIEmbeddings(show_progress_bar=True)
    vectorstore = Chroma(persist_directory='./.chroma', embedding_function=embeddings)
    memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history
    llm = ChatOpenAI(temperature=0, model="gpt-3.5-turbo-16k-0613", tiktoken_model_name="cl100k_base")
    #Basic information about the simplrisk database we are querying 
    db_user = "simplerisk"
    db_host = "localhost"
    db_name = "simplerisk"
    sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}")
    tools = load_tools(["terminal"]) # Load up any built in tools needed for the agent
    toolkit = SQLDatabaseToolkit(db=sql_db, llm=llm)
    simplebot = create_sql_agent(llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-16k-0613"), toolkit=toolkit, verbose=True, agent_type=AgentType.ZERO_SHOT_REACT_DESCRIPTION)

# @tool
# def get_sql(query):
#     """Runs a query against an sql database"""
#     agent_executor = create_sql_agent(llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-16k-0613"), toolkit=toolkit, verbose=True, agent_type=AgentType.ZERO_SHOT_REACT_DESCRIPTION)
#     result = agent_executor.run(query)
#     # splitsy = text_splitter.split_text(result)
#     # db = vectorstore.from_texts(splitsy, embedding=embeddings)
#     # final = db.similarity_search(query=query, k=5)

#     return result

def index_db():
    for i in sql_db.get_usable_table_names():
        print(i)
        sql_data = sql_db.run_no_throw(f"SELECT * FROM {i}")
        splitsy = text_splitter.split_text(sql_data)

        if splitsy:
            vectorstore.from_texts(splitsy, embedding=embeddings)
            sleep(2)

    return



