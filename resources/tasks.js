import { listArrItems, sendRequest, sleep } from "./util.js";
import SwalUtils from "./swalutils.js";

const TODO = "inner_t", todoArr = [];
const IN_PROGRESS = "inner_p", inProgressArr = [];
const COMPLETED = "inner_c", completedArr = [];

const DELETE_BUTTON = "deletebutton";
const RENAME_BUTTON = "renamebutton";
const IN_PROGRESS_BUTTON = "inprogressbutton";
const COMPLETED_BUTTON = "completebutton";
const ID_SEPARATOR = "_";

const [textForm, fileForm] = document.getElementsByTagName("form");

const getButtonsOnly = div => [...div.children].filter(node => node.localName === "button");
const renameID = (originalID, taskID) =>
    originalID.substring(0, originalID.lastIndexOf(ID_SEPARATOR) + 1) + taskID;

const addDivToStatusArr = div => {
    switch (div.parentElement.id) {
        case TODO:
            todoArr.push(div);
            break;
        case IN_PROGRESS:
            inProgressArr.push(div);
            break;
        case COMPLETED:
            completedArr.push(div);
            break;
    }
};
const checkArrsForDivAndRemoveIt = (outerDiv, ...arrs) => {
    for (const arr of arrs) {
        const outerDivIndex = arr.indexOf(outerDiv);
        if (outerDivIndex >= 0) {
            arr.splice(outerDivIndex, 1);
        }
    }
};
const handleDuplicateDivIDs = async () => {
    const toggleButtons = isOn => {
        const [, , addTaskButton] = textForm;
        const [, , addTasksButton] = fileForm;
        addTaskButton.disabled = !isOn;
        addTasksButton.disabled = !isOn;
        for (const divArr of [todoArr, inProgressArr, completedArr]) {
            for (const div of divArr) {
                const buttons = getButtonsOnly(div);
                buttons.forEach(button => {button.disabled = !isOn;});
            }
        }
    };
    const hasDupIDs = arr => arr.some((div, i) => div.taskID === (arr[i + 1] && arr[i + 1].taskID));

    const conditions = [
        todoArr.length > 1 && hasDupIDs(todoArr),
        inProgressArr.length > 1 && hasDupIDs(inProgressArr),
        completedArr.length > 1 && hasDupIDs(completedArr)
    ];
    if (conditions.some(cond => cond)) {
        toggleButtons(false);
        const infoObj = {
            buttonIDs: []
        };
        const refreshAndStop = async () => {
            await SwalUtils.toast("Rearranging ids, please try again after the page refreshes...", SwalUtils.INFO, 2000);
            window.location.reload();
            while (true) {
                await sleep(1000);
            }
        };
        const rearrange = async () => {
            SwalUtils.toast("Rearranging ids...", SwalUtils.INFO, 2000);
            const apiTasks = await fetchTasksFromAPI();
            if (!apiTasks) {
                await refreshAndStop();
            }
            const handle = divArr => {
                const divsWithDupIDs = [];
                const dupIDSet = new Set();
                for (const div of divArr) {
                    const taskID = div.taskID;
                    if (!dupIDSet.has(taskID)) {
                        const dups = divArr.filter(div => div.taskID === taskID);
                        if (dups.length > 1) {
                            divsWithDupIDs.push(...dups);
                        }
                        dupIDSet.add(taskID);
                    }
                }
                const dupNamesSet = new Set();
                for (const divWithDupID of divsWithDupIDs) {
                    const divWithDupIDTaskName = divWithDupID.taskName;
                    const sharedTaskNameAPITasks = apiTasks.filter(div => div.task_name === divWithDupIDTaskName);
                    if (sharedTaskNameAPITasks.length === 1) {
                        const [sharedTaskNameAPIObj] = sharedTaskNameAPITasks;
                        divWithDupID.taskID = sharedTaskNameAPIObj.task_id;
                    } else if (sharedTaskNameAPITasks.length > 1 && !dupNamesSet.has(divWithDupIDTaskName)) {
                        const sharedTaskNameDivsWithDups = divsWithDupIDs.filter(div => div.taskName === divWithDupIDTaskName);
                        for (const [i, divWithSharedTaskName] of sharedTaskNameDivsWithDups.entries()) {
                            divWithSharedTaskName.taskID = sharedTaskNameAPITasks[i].task_id;
                        }
                        dupNamesSet.add(divWithDupIDTaskName);
                    }
                    for (const button of getButtonsOnly(divWithDupID)) {
                        button.id = renameID(button.id, divWithDupID.taskID);
                        infoObj.buttonIDs.push(button.id);
                    }
                }
            };
            for (const [i, arr] of [todoArr, inProgressArr, completedArr].entries()) {
                if (conditions[i]) {
                    handle(arr);
                }
                for (const div of arr) {
                    const buttons = getButtonsOnly(div);
                    for (const button of buttons) {
                        const clone = button.cloneNode(true);
                        clone.disabled = false;
                        clone.addEventListener("click", () => {
                            switch (clone.id.split(ID_SEPARATOR)[0]) {
                                case DELETE_BUTTON: {
                                    deleteTask(div);
                                    break;
                                }
                                case RENAME_BUTTON: {
                                    const [innerP] = div.getElementsByTagName("p");
                                    const taskObj = {taskName: innerP.textContent, taskDiv: div};
                                    renameTask(taskObj, div, innerP);
                                    break;
                                }
                                case IN_PROGRESS_BUTTON: {
                                    const buttonIDs = infoObj.buttonIDs;
                                    const newButtonNodeIDNums = buttonIDs
                                        .map(id => Number(id.split(ID_SEPARATOR)[1]));
                                    const newButtonNodeIDNames = buttonIDs
                                        .filter((_, i) => div.taskID === newButtonNodeIDNums[i])
                                        .filter(id => id.startsWith(IN_PROGRESS_BUTTON));
                                    markTaskAsInProgress("", div, newButtonNodeIDNames[0]);
                                    break;
                                }
                                case COMPLETED_BUTTON: {
                                    const buttonIDs = infoObj.buttonIDs;
                                    const newButtonNodeIDNums = buttonIDs
                                        .map(id => Number(id.split(ID_SEPARATOR)[1]));
                                    const newButtonNodeIDNames = buttonIDs
                                        .filter((_, i) => div.taskID === newButtonNodeIDNums[i])
                                        .filter(id => id.startsWith(IN_PROGRESS_BUTTON) || id.startsWith(COMPLETED_BUTTON));
                                    markTaskAsCompleted("", div, newButtonNodeIDNames);
                                    break;
                                }
                            }
                        });
                        div.replaceChild(clone, button);
                    }
                }
            }
        };
        await rearrange();
        toggleButtons(true);
        return infoObj;
    }
    return null;
};

const taskToast = action => {
    SwalUtils.toast(`Task ${action}`, SwalUtils.SUCCESS, 2500);
};
async function deleteTask(outerDiv) {
    await handleDuplicateDivIDs();
    sendRequest("index.php", {
        "task_to_delete": true,
        "task_id": outerDiv.taskID
    });
    const taskStatusDivIDArr = [TODO, IN_PROGRESS, COMPLETED];
    for (const taskStatusDivID of taskStatusDivIDArr) {
        const taskStatusDiv = document.getElementById(taskStatusDivID);
        if (taskStatusDiv.contains(outerDiv)) {
            taskStatusDiv.removeChild(outerDiv);
        }
    }
    checkArrsForDivAndRemoveIt(outerDiv, todoArr, inProgressArr, completedArr);
    taskToast("deleted");
}
async function renameTask(taskObj, outerDiv, innerP) {
    const renamedTask = await SwalUtils.prompt("Rename this task to what?", {defaultValue: taskObj.taskName});
    if (renamedTask && renamedTask !== taskObj.taskName) {
        await handleDuplicateDivIDs();
        sendRequest("index.php", {
            "task_to_rename": taskObj.taskName,
            "new_task_name": renamedTask,
            "task_id": outerDiv.taskID
        });
        innerP.textContent = renamedTask;
        taskObj.taskName = renamedTask;
        taskToast("renamed");
    }
}
async function markTaskAsCompleted(taskName, outerDiv, buttonNodeIDArr) {
    const infoObj = await handleDuplicateDivIDs();
    if (infoObj) {
        const buttonIDs = infoObj.buttonIDs;
        const newButtonNodeIDNums = buttonIDs.map(id => Number(id.split(ID_SEPARATOR)[1]));
        const newButtonNodeIDNames = buttonIDs
            .filter((_, i) => outerDiv.taskID === newButtonNodeIDNums[i])
            .filter(id => id.startsWith(IN_PROGRESS_BUTTON) || id.startsWith(COMPLETED_BUTTON));
        buttonNodeIDArr = newButtonNodeIDNames;
    }
    sendRequest("index.php", {
        "task_to_mark_complete": taskName,
        "task_id": outerDiv.taskID
    });
    for (const buttonNodeID of buttonNodeIDArr) {
        const buttonNode = document.getElementById(buttonNodeID);
        if (outerDiv.contains(buttonNode)) {
            buttonNode.remove();
        }
    }
    completedArr.push(outerDiv);
    completedArr.sort((a, b) => a.taskID - b.taskID);
    const completed = document.getElementById(COMPLETED);
    for (const divNode of completedArr) {
        completed.appendChild(divNode);
    }
    checkArrsForDivAndRemoveIt(outerDiv, todoArr, inProgressArr);
    taskToast("marked as completed");
}
async function markTaskAsInProgress(taskName, outerDiv, IN_PROGRESS_BUTTON_ID) {
    const infoObj = await handleDuplicateDivIDs();
    if (infoObj) {
        const buttonIDs = infoObj.buttonIDs;
        const newButtonNodeIDNums = buttonIDs.map(id => Number(id.split(ID_SEPARATOR)[1]));
        const newButtonNodeIDNames = buttonIDs
            .filter((_, i) => outerDiv.taskID === newButtonNodeIDNums[i])
            .filter(id => id.startsWith(IN_PROGRESS_BUTTON));
        [IN_PROGRESS_BUTTON_ID] = newButtonNodeIDNames;
    }
    sendRequest("index.php", {
        "task_to_mark_in_progress": taskName,
        "task_id": outerDiv.taskID
    });
    document.getElementById(IN_PROGRESS_BUTTON_ID).remove();
    inProgressArr.push(outerDiv);
    inProgressArr.sort((a, b) => a.taskID - b.taskID);
    const inProgress = document.getElementById(IN_PROGRESS);
    for (const divNode of inProgressArr) {
        inProgress.appendChild(divNode);
    }
    checkArrsForDivAndRemoveIt(outerDiv, todoArr);
    taskToast("marked as in progress");
}

async function handleJSError(id) {
    console.error("handleJSError", id);
    await SwalUtils.alert("A problem occured when attempting to display the newly " +
            "created task. The page will now refresh.\n" +
            "Your task was inserted successfully and will be shown once " +
            "the page refreshes.", SwalUtils.ERROR);
    window.location.reload();
}
async function fetchTasksFromAPI() {
    const apiResponse = await sendRequest("api.php");
    if (apiResponse.status !== 200) {
        console.log(`apiResponse.status === ${apiResponse.status}`);
        return false;
    }
    const tasks = (await apiResponse.json()).tasks;
    if (tasks.length === 0) {
        console.log("tasks.length === 0");
        return false;
    }
    return tasks;
}

export function displayTask(task, div, task_id, needsToLoad) {
    const attachButton = (node, name, callback, id = "") => {
        const button = document.createElement("button");
        button.textContent = name;
        button.id = id;
        button.classList.add("button");
        button.addEventListener("click", callback);
        if (task_id === 0) {
            button.disabled = true;
        }
        node.appendChild(button);
    };

    const DELETE_BUTTON_ID = `${DELETE_BUTTON}${ID_SEPARATOR}${task_id}`;
    const RENAME_BUTTON_ID = `${RENAME_BUTTON}${ID_SEPARATOR}${task_id}`;
    const COMPLETE_BUTTON_ID = `${COMPLETED_BUTTON}${ID_SEPARATOR}${task_id}`;
    const IN_PROGRESS_BUTTON_ID = `${IN_PROGRESS_BUTTON}${ID_SEPARATOR}${task_id}`;

    const outerDiv = document.createElement("div");
    const innerP = document.createElement("p");
    const br = document.createElement("br");

    innerP.textContent = task;
    innerP.style.all = "unset";

    outerDiv.style.fontSize = "20px";
    outerDiv.style.marginTop = "15px";
    outerDiv.appendChild(innerP);
    let loadingGIF = null;
    if (needsToLoad) {
        loadingGIF = document.createElement("img");
        loadingGIF.src = "./resources/loading.gif";
        loadingGIF.className = "loadinggif";
        loadingGIF.style.marginLeft = "5px";
        outerDiv.appendChild(loadingGIF);
    }
    outerDiv.appendChild(br);

    attachButton(outerDiv, "Delete task", () => deleteTask(outerDiv), DELETE_BUTTON_ID);
    const taskObj = {taskName: task, taskDiv: outerDiv};
    attachButton(outerDiv, "Rename task", async () => await renameTask(taskObj, outerDiv, innerP), RENAME_BUTTON_ID);
    if (div === TODO || div === IN_PROGRESS) {
        attachButton(outerDiv, "Mark as completed", () => markTaskAsCompleted(task, outerDiv, [IN_PROGRESS_BUTTON_ID, COMPLETE_BUTTON_ID]), COMPLETE_BUTTON_ID);
    }
    if (div === TODO) {
        attachButton(outerDiv, "Mark as in progress", () => markTaskAsInProgress(task, outerDiv, IN_PROGRESS_BUTTON_ID), IN_PROGRESS_BUTTON_ID);
    }

    document.getElementById(div).appendChild(outerDiv);
    outerDiv.taskName = task;
    if (task_id !== 0) {
        outerDiv.taskID = task_id;
        addDivToStatusArr(outerDiv);
    }
    return {
        taskDiv: outerDiv,
        loadingGIF: loadingGIF,
        innerP: innerP,
        deleteButtonID: DELETE_BUTTON_ID,
        renameButtonID: RENAME_BUTTON_ID,
        completeButtonID: COMPLETE_BUTTON_ID,
        inProgressButtonID: IN_PROGRESS_BUTTON_ID
    };
}

textForm.addEventListener("submit", async e => {
    e.preventDefault();
    const [input, dropdown] = textForm.children;
    if (input.value.trim() !== "") {
        const inputValue = input.value;
        input.value = null;
        taskToast("added successfully");

        const dropdownValue = dropdown.value;
        const {
            taskDiv,
            loadingGIF,
            innerP,
            deleteButtonID,
            renameButtonID, 
            completeButtonID,
            inProgressButtonID
        } = displayTask(
            inputValue,
            dropdownValue === "in_progress"
                ? IN_PROGRESS : (dropdownValue === "completed" ? COMPLETED : TODO), 0, true
        );
        await sendRequest("index.php", {
            "entertask": inputValue,
            "task_status": dropdownValue
        });

        const apiTasks = await fetchTasksFromAPI();
        if (!apiTasks) {
            handleJSError(1);
            return;
        }
        
        const {task_id: taskID} = apiTasks[apiTasks.length - 1];
        const taskName = taskDiv.taskName;
        const taskObj = {taskName: taskName, taskDiv: taskDiv};
        const newIDArr = [
            renameID(deleteButtonID, taskID),
            renameID(renameButtonID, taskID),
            renameID(completeButtonID, taskID),
            renameID(inProgressButtonID, taskID)
        ]
        const funcs = [
            {
                func: deleteTask,
                args: [taskDiv]
            },
            {
                func: renameTask,
                args: [taskObj, taskDiv, innerP]
            },
            {
                func: markTaskAsCompleted,
                args: [taskName, taskDiv, [newIDArr[3], newIDArr[2]]]
            },
            {
                func: markTaskAsInProgress,
                args: [taskName, taskDiv, newIDArr[3]]
            }
        ];
        const buttons = getButtonsOnly(taskDiv);
        for (const [i, button] of buttons.entries()) {
            const clone = button.cloneNode(true);
            clone.addEventListener("click", () => funcs[i].func(...funcs[i].args));
            clone.disabled = false;
            clone.id = newIDArr[i] || clone.id;
            taskDiv.replaceChild(clone, button);
        }
        if (loadingGIF) taskDiv.removeChild(loadingGIF);
        taskDiv.taskID = taskID;
        addDivToStatusArr(taskDiv);
    }
});
fileForm.addEventListener("submit", async e => {
    e.preventDefault();
    const [fileTag, dropdown] = fileForm.children;
    if (fileTag.files.length === 0) {
        SwalUtils.bigAlert("Please choose a file.", SwalUtils.ERROR);
        return;
    }

    const [file] = fileTag.files;
    fileTag.value = null;
    const fileExtension = file.name.substring(file.name.lastIndexOf(".") + 1);
    const allowedFileTypes = [
        "csv",
        "log",
        "txt"
    ];
    if (!allowedFileTypes.includes(fileExtension)) {
        SwalUtils.alert(`File type not supported. Supported file types include<br> ${"<b>"+listArrItems(allowedFileTypes)+"</b>"}.`, SwalUtils.ERROR);
        return;
    }
    SwalUtils.toast("Tasks added successfully", SwalUtils.SUCCESS, 3000);

    const reader = new FileReader();
    reader.addEventListener("loadend", async () => {
        let fileTextArr;
        switch (fileExtension) {
            case "csv":
                fileTextArr = reader.result.split(/[,|\r|\n|\r\n]/);
                break;
            case "log":
            case "txt":
                fileTextArr = reader.result.split(/[\r|\n|\r\n]/)
                break;
        }
        fileTextArr = fileTextArr.filter(str => str.length > 0);

        const dropdownValue = dropdown.value;
        const divToInsertAt = dropdownValue === "in_progress"
            ? IN_PROGRESS
            : (dropdownValue === "completed" ? COMPLETED : TODO);
        const taskDivs = [];
        for (const task of fileTextArr) {
            const taskDivObj = displayTask(task, divToInsertAt, 0, true);
            taskDivs.push(taskDivObj);
        }

        const formData = new FormData(fileForm);
        formData.append("uploadfile", file);
        formData.append("task_status", dropdownValue);
        await fetch("index.php", {
            method: "POST",
            body: formData
        });

        const apiTasks = await fetchTasksFromAPI();
        if (!apiTasks) {
            handleJSError(2);
            return;
        }

        for (const {
            taskDiv,
            loadingGIF,
            innerP,
            deleteButtonID,
            renameButtonID,
            completeButtonID,
            inProgressButtonID 
        } of taskDivs) {
            const buttons = getButtonsOnly(taskDiv);
            const {task_id: taskID} = apiTasks[apiTasks.length - 1];
            const taskName = taskDiv.taskName;
            const taskObj = {taskName: taskName, taskDiv: taskDiv};
            const newIDArr = [
                renameID(deleteButtonID, taskID),
                renameID(renameButtonID, taskID),
                renameID(completeButtonID, taskID),
                renameID(inProgressButtonID, taskID)
            ]
            const funcs = [
                {
                    func: deleteTask,
                    args: [taskDiv]
                },
                {
                    func: renameTask,
                    args: [taskObj, taskDiv, innerP]
                },
                {
                    func: markTaskAsCompleted,
                    args: [taskName, taskDiv, [newIDArr[3], newIDArr[2]]]
                },
                {
                    func: markTaskAsInProgress,
                    args: [taskName, taskDiv, newIDArr[3]]
                }
            ];
            for (const [i, button] of buttons.entries()) {
                const clone = button.cloneNode(true);
                clone.disabled = false;
                clone.addEventListener("click", () => funcs[i].func(...funcs[i].args));
                clone.id = newIDArr[i] || clone.id;
                taskDiv.replaceChild(clone, button);
            }
            if (loadingGIF) taskDiv.removeChild(loadingGIF);
            taskDiv.taskID = taskID;

            addDivToStatusArr(taskDiv);
        }
    });
    
    reader.readAsText(file);
});