#!/usr/bin/env python3
import json
from datetime import datetime, timedelta, timezone
from pathlib import Path

from fsrs import Card, Rating, Scheduler


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

Path(__file__).with_name("fsrs_v6_3_0.json").write_text(json.dumps(cases, indent=2) + "\n")
