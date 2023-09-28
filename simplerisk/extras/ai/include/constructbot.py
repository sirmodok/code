from langchain.agents import Tool, tool, AgentExecutor, LLMSingleActionAgent, AgentOutputParser
from langchain.prompts import StringPromptTemplate
from langchain.chains import LLMChain
from langchain.llms import OpenAI
from langchain.tools import DuckDuckGoSearchRun 
from typing import List, Union
from langchain.schema import AgentAction, AgentFinish
import re

get_framework_template = """
Download the requirements for the requested secure controls framework

You have access to the following tools:
{tools}

For each control in the framework look up the following information
control short name 
control long name
control number
control description
supplemental guidance
control priority
control class
control phase
control family


Action: Choose one of the available tools in [{tool_names}] for your action
Action Input: Provide the input required for the chosen tool
Final Answer: Provide your final answer from the perspective of an experienced risk management consultant

Let's get started!
Question: {input}
{agent_scratchpad}"""

class CustomPromptTemplate(StringPromptTemplate):
    template: str
    tools: List[Tool]
    
    def format(self, **kwargs) -> str:
        intermediate_steps = kwargs.pop("intermediate_steps")
        thoughts = ""
        for action, observation in intermediate_steps:
            thoughts += action.log
            thoughts += f"\nObservation: {observation}\nThought: "
        kwargs["agent_scratchpad"] = thoughts
        kwargs["tools"] = "\n".join([f"{tool.name}: {tool.description}" for tool in self.tools])
        kwargs["tool_names"] = ", ".join([tool.name for tool in self.tools])
        return self.template.format(**kwargs)

class CustomOutputParser(AgentOutputParser):
    
    def parse(self, llm_output: str) -> Union[AgentAction, AgentFinish]:
        if "Final Answer:" in llm_output:
            return AgentFinish(
                return_values={"output": llm_output.split("Final Answer:")[-1].strip()},
                log=llm_output,
            )
        regex = r"Action\s*\d*\s*:(.*?)\nAction\s*\d*\s*Input\s*\d*\s*:[\s]*(.*)"
        match = re.search(regex, llm_output, re.DOTALL)
        action = match.group(1).strip()
        action_input = match.group(2)
        return AgentAction(tool=action, tool_input=action_input.strip(" ").strip('"'), log=llm_output)

@tool("scf_search", return_direct=True)
def scf_search(input_text):
    """Use to search the internet"""
    search = DuckDuckGoSearchRun().run(f"{input_text}")
    print(search)
    return search 

def agent():
    tools = [
        Tool(name = "scf_search", func=scf_search, description="useful for when you need to find requirements for secure control frameworks"),
        ]
    prompt = CustomPromptTemplate(template=get_framework_template, tools=tools, input_variables=["input", "intermediate_steps"])
    output_parser = CustomOutputParser()
    llm = OpenAI(temperature=0)
    llm_chain = LLMChain(llm=llm, prompt=prompt)
    tool_names = [tool.name for tool in tools]
    agent = LLMSingleActionAgent(llm_chain=llm_chain, output_parser=output_parser, stop=["\nObservation:"], allowed_tools=tool_names)
    agent_executor = AgentExecutor.from_agent_and_tools(agent=agent, tools=tools, verbose=True)
    return agent_executor

