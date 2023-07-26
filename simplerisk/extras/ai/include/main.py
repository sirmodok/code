import os
from langchain.agents import initialize_agent
from langchain.agents import tool
from langchain.agents import load_tools
from langchain.memory import ConversationBufferMemory
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.agents import AgentType
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.embeddings import OpenAIEmbeddings
from langchain.vectorstores import Chroma
from langchain.prompts import StringPromptTemplate

from langchain.agents import ZeroShotAgent

text_splitter = RecursiveCharacterTextSplitter(
    # Set a really small chunk size, just to show.
    chunk_size = 4097,
    chunk_overlap  = 20,
    length_function = len,
    add_start_index = True,
)
embeddings = OpenAIEmbeddings()
vectorstore = Chroma()
openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD
memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history

#Basic information about the simplrisk database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

class SimpleAgent:
    def __init__(self):

        @tool
        def get_sql(query):
            """Runs a query against an sql database"""
            try:
                result = self.sql_db.run(query)
                splitsy = text_splitter.split_text(result)
                db = vectorstore.from_texts(splitsy, embedding=embeddings)
                final = db.similarity_search(query=query)
            except:
                final = "There was an error retrieving SQL information"
            return final
        
        self.sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}") # Create a langchain owned object of our database
        tools = load_tools([]) # Load up any built in tools needed for the agent
        tools.append(get_sql)
        llm = ChatOpenAI(temperature=0, model="gpt-4")
        self.agent = initialize_agent(llm=llm, tools=tools, verbose=True, agent=AgentType.CHAT_CONVERSATIONAL_REACT_DESCRIPTION, memory=memory)


if __name__ == "__main__":
    print("start")
    the_agent = SimpleAgent()
    print("agent created")
    while True:
        user_input = input("Enter question: ")
        answer = the_agent.agent.run(input=user_input)
        print("\n\n\n\n")
        print(answer)