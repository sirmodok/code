import os
from langchain.agents import OpenAIFunctionsAgent, tool
from langchain.agents import load_tools
from langchain.memory import ConversationBufferMemory
from langchain.chat_models import ChatOpenAI
from langchain.sql_database import SQLDatabase
from langchain.agents import AgentExecutor
from langchain.schema import SystemMessage



openai_api_key = os.getenv("OPENAI_API_KEY") # Get the openai api key from the OS. Store your openai api key in OPENAI_API_KEY
sql_db_password = os.getenv("SQL_DB_PASSWORD") # Get the SQL database password. Store the database password in SQL_DB_PASSWORD



#Basic information about the database we are querying 
db_user = "simplerisk"
db_host = "localhost"
db_name = "simplerisk"

class SimpleAgent:
    def __init__(self):
        self.sql_db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{sql_db_password}@{db_host}/{db_name}") # Create a langchain owned object of our database
        memory = ConversationBufferMemory(memory_key="chat_history", return_messages=True) # Create a memory object that stores our conversation history

        # This is a tool created to work with an sql database
        @tool
        def get_sql(query):
            """Runs a query against an sql database"""
            result = self.sql_db.run(query)
            return result

        tools = load_tools(["terminal"]) # Load up any built in tools needed for the agent
        tools.append(get_sql) # Appends our sql_query tool
        system_message = SystemMessage(content="You are an assistant that helps with the SimpleRisk application. SimpleRisk is designed to help people maintain security risks in their environment. You will mainly be communicating with an sql database with the schema of 'simplerisk'")
        prompt = OpenAIFunctionsAgent.create_prompt(system_message=system_message)
        main_agent = OpenAIFunctionsAgent(llm=ChatOpenAI(temperature=0, model="gpt-3.5-turbo-0613"), tools=tools, prompt=prompt)
        self.main_agent_executor = AgentExecutor(agent=main_agent, tools=tools, verbose=True, memory=memory)


if __name__ == "__main__":
    print("start")
    the_agent = SimpleAgent()
    print("agent created")
    the_agent.main_agent_executor.run("How many tables are in the simplerisk database")













    # # Iterate over those table names and for each table, get all the columns and rows
    # for i in tables:
    #     sql_query = the_agent.sql_db.run_no_throw("""SELECT * FROM {}""".format(i))
    #     full_list.append(sql_query)
    # split_docs = the_agent.text_splitter.create_documents(full_list) # Use our textsplitter object to chunk the text
    # ids = [str(i) for i in range(1, len(split_docs) + 1)]
    # db = Chroma.from_documents(split_docs, SimpleAgent.embeddings, persist_directory="./chroma_db", ids=ids) # Take our chunked documents and use the embeddings object to create vectors of the data and store it into the Chroma vector database stored locally