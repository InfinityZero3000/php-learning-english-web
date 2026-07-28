#!/usr/bin/env python3
import json
from datetime import datetime, timedelta, timezone
from importlib.metadata import version
from pathlib import Path

from fsrs import Card, Rating, Scheduler, State

assert version("fsrs") == "6.3.0"


def card_dict(card):
    return {
        "state": card.state.name.lower(),
        "step": card.step,
        "stability": card.stability,
        "difficulty": card.difficulty,
        "due": card.due.isoformat(),
        "last_review": card.last_review.isoformat() if card.last_review else None,
    }


scheduler = Scheduler(
    desired_retention=0.9,
    learning_steps=(timedelta(minutes=1), timedelta(minutes=10)),
    relearning_steps=(timedelta(minutes=10),),
    maximum_interval=36500,
    enable_fuzzing=False,
)
start = datetime(2026, 1, 1, 12, tzinfo=timezone.utc)
cases = []

for rating in Rating:
    card, _ = scheduler.review_card(Card(due=start), rating, start)
    cases.append({"name": f"new_{rating.name.lower()}", "at": start.isoformat(), "rating": rating.value, "before": card_dict(Card(due=start)), "after": card_dict(card)})

card = Card(due=start)
for index, (days, rating) in enumerate([(0, Rating.Good), (0, Rating.Good), (7, Rating.Hard), (14, Rating.Again), (0, Rating.Good), (30, Rating.Easy)]):
    at = start + timedelta(days=days) if index == 0 else card.last_review + timedelta(days=days)
    before = card_dict(card)
    card, _ = scheduler.review_card(card, rating, at)
    cases.append({"name": f"sequence_{index + 1}", "at": at.isoformat(), "rating": rating.value, "before": before, "after": card_dict(card)})

for state, steps in ((State.Learning, (60, 600)), (State.Relearning, (600,))):
    for step in range(len(steps)):
        for rating in Rating:
            card = Card(
                state=state,
                step=step,
                stability=2.25,
                difficulty=5.5,
                due=start,
                last_review=start,
            )
            reviewed, _ = scheduler.review_card(card, rating, start)
            cases.append({
                "name": f"{state.name.lower()}_step_{step}_{rating.name.lower()}",
                "at": start.isoformat(),
                "rating": rating.value,
                "before": card_dict(card),
                "after": card_dict(reviewed),
            })

maximum = Card(
    state=State.Review,
    step=None,
    stability=100000,
    difficulty=1,
    due=start,
    last_review=start - timedelta(days=365),
)
reviewed, _ = scheduler.review_card(maximum, Rating.Easy, start)
cases.append({
    "name": "maximum_interval",
    "at": start.isoformat(),
    "rating": Rating.Easy.value,
    "before": card_dict(maximum),
    "after": card_dict(reviewed),
})

payload = {
    "package": "fsrs",
    "version": version("fsrs"),
    "cases": cases,
    "interval_cases": [{"stability": 2.5, "days": scheduler._next_interval(stability=2.5)}],
}
Path(__file__).with_name("fsrs_v6_3_0.json").write_text(json.dumps(payload, indent=2) + "\n")
