import os
import pinecone
from langchain.chat_models import ChatOpenAI
from langchain.schema import SystemMessage, HumanMessage
from langchain.sql_database import SQLDatabase

openai_api_key = os.getenv("OPENAI_API_KEY")
pinecone_api_key = os.getenv("PINECONE_API_KEY")

pinecone_location="asia-southeast1-gcp-free"

db_user = "simplerisk"
db_password = "n5j2zHPZ7ZYxuRg19aba"
db_host = "localhost"
db_name = "simplerisk"

llm = ChatOpenAI(temperature=0, model="gpt-4")
db = SQLDatabase.from_uri(f"mysql+pymysql://{db_user}:{db_password}@{db_host}/{db_name}")
pinecone_db = pinecone.init(api_key=pinecone_api_key, environment=pinecone_location)

table_list = db.get_usable_table_names()

for i in table_list:
   j =  db.run_no_throw("SELECT * FROM {}".format(i))
   messages = [
    SystemMessage(content="convert this to json"),
    HumanMessage(content=j)
   ]
   print(llm(messages=messages))
   